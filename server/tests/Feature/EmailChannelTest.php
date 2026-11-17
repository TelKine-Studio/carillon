<?php

namespace Tests\Feature;

use App\Email\DirectTransport;
use App\Email\EmailSender;
use App\Email\Transports;
use App\Enums\ProjectRole;
use App\Http\Controllers\EmailSettingsController;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiAsyncAwsTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class EmailChannelTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Project $project;

    /** @var list<Email> */
    public static array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$sent = [];

        $this->owner = User::factory()->create();
        $this->project = Project::create(['name' => 'Shop', 'created_by' => $this->owner->id]);
        $this->project->addMember($this->owner, ProjectRole::Owner);

        EmailSender::$transportFactory = fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                EmailChannelTest::$sent[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'memory://';
            }
        };
    }

    protected function tearDown(): void
    {
        EmailSender::$transportFactory = null;
        parent::tearDown();
    }

    protected function configure(array $fields): void
    {
        $this->actingAs($this->owner)
            ->put(route('projects.email.update', $this->project), $fields + ['from_address' => 'News@Shop.test', 'from_name' => 'Shop', 'tracking' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->project->load('credentials');
    }

    public function test_every_driver_can_be_configured(): void
    {
        $this->configure(['driver' => 'smtp', 'host' => 'smtp.shop.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'secret' => 'p']);
        $this->assertInstanceOf(EsmtpTransport::class, Transports::make($this->project->credential('email')));
        $this->assertTrue($this->project->credential('email')->isUsable());
        $this->assertSame('news@shop.test', $this->project->credential('email')->detail('from_address'));

        $this->configure(['driver' => 'resend', 'secret' => 're_123']);
        $this->assertInstanceOf(ResendApiTransport::class, Transports::make($this->project->credential('email')));

        $this->configure(['driver' => 'ses', 'aws_key_id' => 'AKIA1', 'region' => 'eu-west-3', 'secret' => 's']);
        $this->assertInstanceOf(SesApiAsyncAwsTransport::class, Transports::make($this->project->credential('email')));

        $this->configure(['driver' => 'postmark', 'secret' => 'pm', 'message_stream' => 'broadcast']);
        $this->assertInstanceOf(PostmarkApiTransport::class, Transports::make($this->project->credential('email')));

        $this->configure(['driver' => 'mailgun', 'domain' => 'mg.shop.test', 'region' => 'eu', 'secret' => 'key']);
        $this->assertInstanceOf(MailgunApiTransport::class, Transports::make($this->project->credential('email')));

        // The secret is kept when the driver stays the same.
        $this->configure(['driver' => 'mailgun', 'domain' => 'mg2.shop.test', 'region' => 'eu']);
        $this->assertSame('key', $this->project->credential('email')->secret);

        $this->configure(['driver' => 'direct']);
        $credential = $this->project->credential('email');
        $this->assertInstanceOf(DirectTransport::class, Transports::make($credential));
        $this->assertSame('shop.test', $credential->detail('dkim_domain'));
        $this->assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $credential->secret);
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=MII', EmailSettingsController::dkimRecord($credential));

        $this->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()
            ->assertSee($credential->detail('dkim_selector').'._domainkey')
            ->assertSee('v=DKIM1', false);

        $this->actingAs($this->owner)->put(route('projects.email.update', $this->project), ['driver' => 'ses', 'from_address' => 'a@b.test', 'aws_key_id' => 'x', 'region' => 'paris'])
            ->assertSessionHasErrors(['region', 'secret']);
    }

    public function test_sending_an_email_message(): void
    {
        $this->configure(['driver' => 'resend', 'secret' => 're_123']);
        [, $key] = ApiKey::generate($this->project, 'test');
        $base = '/api/v1/sdk/apps/'.$this->project->app_id;

        // A phone, and the address of the same person.
        $device = $this->postJson("$base/subscriptions", ['type' => 'ios', 'token' => str_repeat('a', 64), 'external_id' => 'u1', 'tags' => ['first_name' => 'Ada'], 'language' => 'fr'])->json('id');
        $this->postJson("$base/subscriptions/$device/email", ['email' => ' Ada@Example.com '])->assertCreated()->assertJsonPath('email', 'ada@example.com');
        $this->postJson("$base/subscriptions/$device/email", ['email' => 'not-an-email'])->assertStatus(422);

        $id = $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'target_channel' => 'email',
            'included_segments' => ['Subscribed Users'],
            'email_subject' => ['en' => 'Hello', 'fr' => 'Bonjour {{ tags.first_name | default: "toi" }}'],
            'email_preheader' => 'Nouveautés',
            'email_body' => '<html><body><p>Salut {{ tags.first_name }} ({{ email }})</p><a href="https://shop.test/sale?x=1&amp;y=2">Voir</a></body></html>',
        ], ['Authorization' => 'Key '.$key])->assertOk()->assertJsonPath('recipients', 1)->json('id');

        $message = Message::find($id);
        $this->assertSame('email', $message->channel);
        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->successful);

        $this->assertCount(1, self::$sent);
        $email = self::$sent[0];
        $this->assertSame('Bonjour Ada', $email->getSubject());
        $this->assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('Shop', $email->getFrom()[0]->getName());
        $html = $email->getHtmlBody();
        $this->assertStringContainsString('Salut Ada (ada@example.com)', $html);
        $this->assertStringContainsString('Nouveautés', $html);
        $this->assertStringContainsString('Se désinscrire', $html);
        $this->assertStringNotContainsString('href="https://shop.test/sale', $html);
        $this->assertStringContainsString('Salut Ada', $email->getTextBody());
        $unsubscribe = trim($email->getHeaders()->get('List-Unsubscribe')->getBodyAsString(), '<>');
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());

        // Open pixel and tracked click.
        preg_match('#<img src="([^"]+)"#', $html, $pixel);
        $this->get(html_entity_decode($pixel[1]))->assertOk()->assertHeader('Content-Type', 'image/gif');
        preg_match('#<a href="([^"]+)">Voir#', $html, $link);
        $this->get(html_entity_decode($link[1]))->assertRedirect('https://shop.test/sale?x=1&y=2');
        $this->get(html_entity_decode($link[1]).'x')->assertForbidden();
        $message->refresh();
        $this->assertSame([1, 1], [$message->received, $message->clicked]);

        // The message page shows the email (subject with its fallbacks, opens).
        $this->actingAs($this->owner)->get(route('projects.messages.show', [$this->project, $message]))
            ->assertOk()->assertSee('<title>Hello', false)->assertSee('Opened')->assertSee('srcdoc', false);
        $this->assertStringNotContainsString("\n ", $email->getTextBody());

        // A push message does not reach the address, and the other way round.
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->postJson('/api/v1/notifications', ['app_id' => $this->project->app_id, 'included_segments' => ['Subscribed Users'], 'contents' => ['en' => 'Hi']], ['Authorization' => 'Key '.$key])
            ->assertJsonPath('recipients', 1);
        $this->assertCount(1, self::$sent);

        // Unsubscribe page, then one click from a mail app.
        $this->get($unsubscribe)->assertOk()->assertSee('Shop');
        $this->post($unsubscribe, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $address = Subscription::query()->where('type', 'email')->first();
        $this->assertFalse($address->enabled);
        $this->post($unsubscribe, ['resubscribe' => 1])->assertOk()->assertSee('again');
        $this->assertTrue($address->fresh()->enabled);

        // Addresses of the users API and OneSignal include_email_tokens.
        $this->postJson('/api/v1/apps/'.$this->project->app_id.'/users', ['identity' => ['external_id' => 'u2'], 'subscriptions' => [['type' => 'Email', 'token' => 'Bob@Example.com']]], ['Authorization' => 'Key '.$key])->assertCreated();
        $this->postJson('/api/v1/notifications', [
            'app_id' => $this->project->app_id,
            'include_email_tokens' => ['bob@example.com'],
            'email_subject' => 'Direct',
            'email_body' => '<p>For Bob {{ unsubscribe_url }}</p>',
        ], ['Authorization' => 'Key '.$key])->assertOk()->assertJsonPath('recipients', 1);
        $this->assertSame('bob@example.com', self::$sent[1]->getTo()[0]->getAddress());
        $this->assertStringNotContainsString('Unsubscribe</a>', self::$sent[1]->getHtmlBody());

        $this->postJson('/api/v1/notifications', ['app_id' => $this->project->app_id, 'target_channel' => 'email', 'included_segments' => ['All']], ['Authorization' => 'Key '.$key])
            ->assertStatus(400)->assertJsonPath('errors.0', 'Email messages need an email_subject.');
    }

    public function test_bounces_and_complaints(): void
    {
        $this->configure(['driver' => 'resend', 'secret' => 're_123']);
        $secret = $this->project->credential('email')->detail('webhook_secret');
        $subscriber = Subscriber::create(['project_id' => $this->project->id]);
        foreach (['a@x.test', 'b@x.test', 'c@x.test', 'd@x.test'] as $email) {
            Subscription::create(['project_id' => $this->project->id, 'subscriber_id' => $subscriber->id, 'type' => 'email', 'token' => $email, 'enabled' => true]);
        }
        $url = fn ($provider, $s = null) => '/api/v1/email/'.$this->project->app_id.'/'.$provider.'/'.($s ?? $secret);

        $this->postJson($url('resend'), ['type' => 'email.bounced', 'data' => ['to' => ['A@x.test'], 'bounce' => ['type' => 'Permanent']]])->assertOk();
        $this->postJson($url('postmark'), ['RecordType' => 'SpamComplaint', 'Email' => 'b@x.test'])->assertOk();
        $this->postJson($url('mailgun'), ['event-data' => ['event' => 'failed', 'severity' => 'temporary', 'recipient' => 'c@x.test']])->assertOk();
        $this->call('POST', $url('ses'), content: json_encode(['Type' => 'Notification', 'Message' => json_encode(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'd@x.test']]]])]))->assertOk();
        $this->postJson($url('resend', 'wrong'), [])->assertNotFound();

        $state = Subscription::query()->orderBy('token')->get()->map(fn ($s) => [$s->token, $s->enabled, $s->invalid_at !== null])->all();
        $this->assertSame([['a@x.test', true, true], ['b@x.test', false, false], ['c@x.test', true, false], ['d@x.test', true, true]], $state);
        $this->assertSame(['email_bounced', 'email_complaint', null, 'email_bounced'], Subscription::query()->orderBy('token')->pluck('status_reason')->all());

        Http::fake(['sns.eu-west-1.amazonaws.com/*' => Http::response('ok')]);
        $this->call('POST', $url('ses'), content: json_encode(['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/?Action=ConfirmSubscription&Token=t']))->assertOk();
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sns.eu-west-1.amazonaws.com/'));
    }

    public function test_direct_delivery(): void
    {
        $attempts = [];
        $transport = new DirectTransport('shop.test', fn ($domain) => $domain === 'example.com' ? ['mx1.example.com', 'mx2.example.com'] : [], function ($host) use (&$attempts) {
            return new class($host, $attempts) implements TransportInterface
            {
                public function __construct(private string $host, private array &$attempts) {}

                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    $this->attempts[] = $this->host;
                    $to = $envelope->getRecipients()[0]->getAddress();
                    if ($this->host === 'mx1.example.com' && $to === 'ok@example.com') {
                        throw new UnexpectedResponseException('451 4.3.0 Try again later', 451);
                    }
                    if ($to === 'nobody@example.com') {
                        throw new UnexpectedResponseException('550 5.1.1 The email account that you tried to reach does not exist', 550);
                    }

                    return null;
                }

                public function __toString(): string
                {
                    return $this->host;
                }
            };
        });

        $email = fn ($to) => (new Email)->from('news@shop.test')->to($to)->subject('Hi')->text('Hi');

        $transport->send($email('ok@example.com'));
        $this->assertSame(['mx1.example.com', 'mx2.example.com'], $attempts);

        try {
            $transport->send($email('nobody@example.com'));
            $this->fail('Refused');
        } catch (UnexpectedResponseException $e) {
            $this->assertTrue(EmailSender::fromSmtp($e->getCode(), $e->getMessage())->invalid);
        }

        try {
            $transport->send($email('someone@nowhere.test'));
            $this->fail('No mail server');
        } catch (UnexpectedResponseException $e) {
            $this->assertSame(550, $e->getCode());
        }

        $this->assertFalse(EmailSender::fromSmtp(550, '550 5.7.1 Message rejected as spam')->invalid);
        $this->assertSame('errored', EmailSender::fromSmtp(421, '421 Too many connections')->status);
    }

    public function test_port_check_and_settings_page(): void
    {
        $this->actingAs($this->owner)->get(route('projects.settings', [$this->project, 'platforms']))->assertOk()->assertSee('Amazon SES');
        PushCredential::query()->delete();
    }
}
