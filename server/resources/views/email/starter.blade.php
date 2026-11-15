@php
    $open = '{'.'{';
    $close = '}'.'}';
@endphp
<!doctype html>
<html>
<body style="margin:0;background:#f4f4f5;padding:32px 16px;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#18181b">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px">
      <tr><td style="padding:32px">
        <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3">{{ __('Hello') }} {{ $open }} tags.first_name | default: "{{ __('there') }}" {{ $close }},</h1>
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#3f3f46">{{ __('Write your message here.') }}</p>
        <a href="https://example.com" style="display:inline-block;background:#e4531e;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 20px;border-radius:6px">{{ __('Discover') }}</a>
      </td></tr>
    </table>
    <p style="margin:24px 0 0;font-size:12px;color:#a1a1aa"><a href="{{ $open }} unsubscribe_url {{ $close }}" style="color:#a1a1aa">{{ __('Unsubscribe') }}</a></p>
  </td></tr></table>
</body>
</html>
