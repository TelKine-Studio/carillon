import 'package:carillon_flutter/carillon_flutter.dart';
import 'package:flutter/material.dart';

/// Run with your project's App ID and your Carillon server:
///   flutter run --dart-define=CARILLON_APP_ID=… --dart-define=CARILLON_SERVER=https://carillon.example.com
const appId = String.fromEnvironment('CARILLON_APP_ID');
const server = String.fromEnvironment('CARILLON_SERVER', defaultValue: 'http://localhost:8000');

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (appId.isNotEmpty) await Carillon.initialize(appId, serverUrl: server);
  runApp(const ExampleApp());
}

class ExampleApp extends StatelessWidget {
  const ExampleApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Carillon',
      theme: ThemeData(colorSchemeSeed: const Color(0xFF4F46E5), useMaterial3: true),
      darkTheme: ThemeData(colorSchemeSeed: const Color(0xFF4F46E5), brightness: Brightness.dark, useMaterial3: true),
      home: appId.isEmpty ? const _MissingConfig() : const HomePage(),
    );
  }
}

class _MissingConfig extends StatelessWidget {
  const _MissingConfig();

  @override
  Widget build(BuildContext context) => const Scaffold(
    body: Center(
      child: Padding(
        padding: EdgeInsets.all(24),
        child: Text('Pass --dart-define=CARILLON_APP_ID=… (and CARILLON_SERVER) to flutter run.', textAlign: TextAlign.center),
      ),
    ),
  );
}

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final _externalId = TextEditingController();
  final _events = <String>[];
  bool _permission = false;

  @override
  void initState() {
    super.initState();
    _externalId.text = Carillon.user.externalId ?? '';
    Carillon.notifications.addClickListener((event) {
      _log('Clicked: ${event.notification.title ?? event.notification.body}'
          '${event.actionId != null ? ' (button ${event.actionId})' : ''}');
    });
    Carillon.notifications.addForegroundWillDisplayListener((event) {
      _log('Received in the foreground: ${event.notification.title ?? event.notification.body}');
    });
    _refresh();
  }

  Future<void> _refresh() async {
    final permission = await Carillon.notifications.permission;
    if (mounted) setState(() => _permission = permission);
  }

  void _log(String event) {
    if (!mounted) return;
    setState(() => _events.insert(0, '${TimeOfDay.now().format(context)}  $event'));
  }

  @override
  Widget build(BuildContext context) {
    final subscription = Carillon.user.pushSubscription;
    final tags = Carillon.user.tags;

    return Scaffold(
      appBar: AppBar(title: const Text('Carillon')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _Section(
              title: 'Device',
              children: [
                _Row('Permission', _permission ? 'Granted' : 'Not granted'),
                _Row('Subscription', subscription.id ?? '—'),
                _Row('Opted in', subscription.optedIn ? 'Yes' : 'No'),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    FilledButton(
                      onPressed: () async {
                        await Carillon.notifications.requestPermission();
                        await _refresh();
                      },
                      child: const Text('Allow notifications'),
                    ),
                    OutlinedButton(
                      onPressed: () async {
                        subscription.optedIn ? await subscription.optOut() : await subscription.optIn();
                        setState(() {});
                      },
                      child: Text(subscription.optedIn ? 'Opt out' : 'Opt in'),
                    ),
                  ],
                ),
              ],
            ),
            _Section(
              title: 'User',
              children: [
                TextField(
                  controller: _externalId,
                  decoration: const InputDecoration(labelText: 'External id', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    FilledButton(
                      onPressed: () async {
                        await Carillon.login(_externalId.text.trim());
                        _log('Logged in as ${_externalId.text.trim()}');
                      },
                      child: const Text('Log in'),
                    ),
                    OutlinedButton(
                      onPressed: () async {
                        await Carillon.logout();
                        _externalId.clear();
                        _log('Logged out');
                      },
                      child: const Text('Log out'),
                    ),
                  ],
                ),
              ],
            ),
            _Section(
              title: 'Tags',
              children: [
                if (tags.isEmpty) const Text('No tags.'),
                for (final entry in tags.entries) _Row(entry.key, entry.value),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    OutlinedButton(
                      onPressed: () async {
                        await Carillon.user.addTags({'plan': 'pro', 'example': true});
                        setState(() {});
                      },
                      child: const Text('Add plan=pro'),
                    ),
                    OutlinedButton(
                      onPressed: () async {
                        await Carillon.user.removeTags(['plan', 'example']);
                        setState(() {});
                      },
                      child: const Text('Remove'),
                    ),
                  ],
                ),
              ],
            ),
            _Section(
              title: 'Events',
              children: [
                if (_events.isEmpty) const Text('Send a notification from the dashboard.'),
                for (final event in _events) Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Text(event)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 24),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        ...children,
      ],
    ),
  );
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(width: 110, child: Text(label, style: TextStyle(color: Theme.of(context).hintColor))),
        Expanded(child: SelectableText(value)),
      ],
    ),
  );
}
