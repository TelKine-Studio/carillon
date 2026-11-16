import 'dart:async';

import 'package:flutter/material.dart';

/// An in-app message as sent by the server.
class InAppMessage {
  InAppMessage(this.data);

  final Map<String, dynamic> data;

  String get id => data['id'] as String;
  String get layout => (data['layout'] as String?) ?? 'modal';
  String? get title => data['title'] as String?;
  String get body => (data['body'] as String?) ?? '';
  String? get imageUrl => data['image_url'] as String?;
  Map<String, dynamic> get trigger =>
      Map<String, dynamic>.from((data['trigger'] as Map?) ?? const {});
  String get frequency => (data['frequency'] as String?) ?? 'once';
  int get frequencyDays => (data['frequency_days'] as num?)?.toInt() ?? 1;

  List<Map<String, dynamic>> get buttons => [
    for (final button in (data['buttons'] as List?) ?? const [])
      Map<String, dynamic>.from(button as Map),
  ];
}

/// What the person did with a message.
enum InAppOutcome { clicked, dismissed }

/// Shows a message above the app; completes with the outcome and the button.
Future<(InAppOutcome, Map<String, dynamic>?)> showInAppMessage(
  NavigatorState navigator,
  InAppMessage message,
) {
  return message.layout == 'banner'
      ? _showBanner(navigator, message)
      : _showDialog(navigator, message);
}

Future<(InAppOutcome, Map<String, dynamic>?)> _showDialog(
  NavigatorState navigator,
  InAppMessage message,
) async {
  final fullscreen = message.layout == 'fullscreen';
  final result = await showGeneralDialog<Map<String, dynamic>>(
    context: navigator.context,
    barrierDismissible: !fullscreen,
    barrierLabel: MaterialLocalizations.of(navigator.context)
        .modalBarrierDismissLabel,
    barrierColor: Colors.black54,
    transitionDuration: const Duration(milliseconds: 260),
    transitionBuilder: (context, animation, _, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutBack,
        reverseCurve: Curves.easeIn,
      );
      return FadeTransition(
        opacity: animation,
        child: ScaleTransition(
          scale: Tween(begin: 0.92, end: 1.0).animate(curved),
          child: child,
        ),
      );
    },
    pageBuilder: (context, _, _) =>
        _MessageCard(message: message, fullscreen: fullscreen),
  );

  return result == null
      ? (InAppOutcome.dismissed, null)
      : (InAppOutcome.clicked, result);
}

Future<(InAppOutcome, Map<String, dynamic>?)> _showBanner(
  NavigatorState navigator,
  InAppMessage message,
) {
  final overlay = navigator.overlay;
  final completer = Completer<(InAppOutcome, Map<String, dynamic>?)>();
  if (overlay == null) {
    completer.complete((InAppOutcome.dismissed, null));
    return completer.future;
  }

  late OverlayEntry entry;
  void close(InAppOutcome outcome) {
    if (completer.isCompleted) return;
    entry.remove();
    completer.complete((
      outcome,
      outcome == InAppOutcome.clicked ? const {'id': 'banner'} : null,
    ));
  }

  entry = OverlayEntry(
    builder: (context) => _Banner(
      message: message,
      onTap: () => close(InAppOutcome.clicked),
      onClose: () => close(InAppOutcome.dismissed),
    ),
  );
  overlay.insert(entry);
  Timer(const Duration(seconds: 7), () => close(InAppOutcome.dismissed));

  return completer.future;
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({required this.message, required this.fullscreen});

  final InAppMessage message;
  final bool fullscreen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final buttons = message.buttons;

    final content = Column(
      mainAxisSize: fullscreen ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (message.imageUrl != null)
          Image.network(
            message.imageUrl!,
            height: fullscreen ? 260 : 160,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const SizedBox.shrink(),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 8),
          child: Column(
            children: [
              if (message.title != null)
                Text(
                  message.title!,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              if (message.title != null) const SizedBox(height: 8),
              Text(
                message.body,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
          child: Column(
            children: [
              for (final (index, button) in buttons.indexed) ...[
                if (index > 0) const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: index == 0
                      ? FilledButton(
                          onPressed: () => Navigator.of(context).pop(button),
                          child: Text('${button['text']}'),
                        )
                      : TextButton(
                          onPressed: () => Navigator.of(context).pop(button),
                          child: Text('${button['text']}'),
                        ),
                ),
              ],
              if (buttons.isEmpty)
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: Text(
                    MaterialLocalizations.of(context).closeButtonLabel,
                  ),
                ),
            ],
          ),
        ),
      ],
    );

    if (fullscreen) {
      return Material(
        color: theme.colorScheme.surface,
        child: SafeArea(
          child: Stack(
            children: [
              Center(child: SingleChildScrollView(child: content)),
              Positioned(
                top: 4,
                right: 4,
                child: IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: Material(
            color: theme.colorScheme.surface,
            borderRadius: BorderRadius.circular(16),
            clipBehavior: Clip.antiAlias,
            elevation: 8,
            child: content,
          ),
        ),
      ),
    );
  }
}

class _Banner extends StatefulWidget {
  const _Banner({
    required this.message,
    required this.onTap,
    required this.onClose,
  });

  final InAppMessage message;
  final VoidCallback onTap;
  final VoidCallback onClose;

  @override
  State<_Banner> createState() => _BannerState();
}

class _BannerState extends State<_Banner> with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 320),
  )..forward();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Positioned(
      top: 0,
      left: 0,
      right: 0,
      child: SafeArea(
        child: SlideTransition(
          position: Tween(begin: const Offset(0, -1.2), end: Offset.zero)
              .animate(
                CurvedAnimation(
                  parent: _controller,
                  curve: Curves.easeOutCubic,
                ),
              ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Dismissible(
              key: ValueKey(widget.message.id),
              direction: DismissDirection.up,
              onDismissed: (_) => widget.onClose(),
              child: Material(
                elevation: 6,
                borderRadius: BorderRadius.circular(14),
                color: theme.colorScheme.surface,
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: widget.onTap,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              if (widget.message.title != null)
                                Text(
                                  widget.message.title!,
                                  style: theme.textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              Text(
                                widget.message.body,
                                style: theme.textTheme.bodySmall,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close, size: 18),
                          onPressed: widget.onClose,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
