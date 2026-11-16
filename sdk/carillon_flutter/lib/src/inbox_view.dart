import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'carillon.dart';
import 'models.dart';

/// A ready-made notification center: the messages of [Carillon.inbox], pull
/// to refresh, swipe to remove, unread ones highlighted. Put it in a page:
///
/// ```dart
/// Scaffold(appBar: AppBar(title: Text('Notifications')), body: CarillonInboxView())
/// ```
class CarillonInboxView extends StatefulWidget {
  const CarillonInboxView({
    super.key,
    this.onTap,
    this.emptyText = 'No notification yet',
    this.markAllReadOnOpen = false,
  });

  /// Called when a message is tapped (after it is marked as read). Without
  /// it, a web link of the message is opened.
  final void Function(CarillonInboxMessage message)? onTap;

  final String emptyText;

  /// Marks everything as read when the view opens.
  final bool markAllReadOnOpen;

  @override
  State<CarillonInboxView> createState() => _CarillonInboxViewState();
}

class _CarillonInboxViewState extends State<CarillonInboxView> {
  late Future<void> _loading;

  @override
  void initState() {
    super.initState();
    _loading = Carillon.inbox.refresh().then((_) async {
      if (widget.markAllReadOnOpen && Carillon.inbox.unreadCount.value > 0) {
        await Carillon.inbox.markAllRead();
      }
    });
  }

  Future<void> _open(CarillonInboxMessage message) async {
    if (!message.read) await Carillon.inbox.markRead([message.id]);
    if (widget.onTap != null) {
      widget.onTap!(message);
      return;
    }
    final uri = message.url == null ? null : Uri.tryParse(message.url!);
    if (uri != null && uri.hasScheme) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return FutureBuilder<void>(
      future: _loading,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        return ValueListenableBuilder<List<CarillonInboxMessage>>(
          valueListenable: Carillon.inbox.messages,
          builder: (context, messages, _) => RefreshIndicator(
            onRefresh: Carillon.inbox.refresh,
            child: messages.isEmpty
                ? ListView(
                    children: [
                      const SizedBox(height: 120),
                      Icon(
                        Icons.notifications_none,
                        size: 48,
                        color: theme.colorScheme.outline,
                      ),
                      const SizedBox(height: 12),
                      Text(
                        widget.emptyText,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.outline,
                        ),
                      ),
                    ],
                  )
                : ListView.separated(
                    itemCount: messages.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      return Dismissible(
                        key: ValueKey(message.id),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 24),
                          color: theme.colorScheme.errorContainer,
                          child: Icon(
                            Icons.delete_outline,
                            color: theme.colorScheme.onErrorContainer,
                          ),
                        ),
                        onDismissed: (_) => Carillon.inbox.remove([message.id]),
                        child: _Tile(
                          message: message,
                          onTap: () => _open(message),
                        ),
                      );
                    },
                  ),
          ),
        );
      },
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.message, required this.onTap});

  final CarillonInboxMessage message;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final unread = !message.read;
    return Material(
      color: unread
          ? theme.colorScheme.primaryContainer.withValues(alpha: 0.25)
          : Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: theme.colorScheme.surfaceContainerHighest,
                foregroundImage: message.icon != null
                    ? NetworkImage(message.icon!)
                    : null,
                child: Icon(
                  Icons.notifications_outlined,
                  size: 20,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            message.title ?? message.body ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: unread
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _ago(message.sentAt),
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.outline,
                          ),
                        ),
                      ],
                    ),
                    if (message.title != null && message.body != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        message.body!,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                    if (message.image != null) ...[
                      const SizedBox(height: 8),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          message.image!,
                          height: 140,
                          width: double.infinity,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => const SizedBox.shrink(),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (unread) ...[
                const SizedBox(width: 8),
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: CircleAvatar(
                    radius: 4,
                    backgroundColor: theme.colorScheme.primary,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  /// "5 min", "3 h", "2 d", else the date.
  static String _ago(DateTime date) {
    final age = DateTime.now().difference(date);
    if (age.inMinutes < 1) return 'now';
    if (age.inHours < 1) return '${age.inMinutes} min';
    if (age.inDays < 1) return '${age.inHours} h';
    if (age.inDays < 7) return '${age.inDays} d';
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}';
  }
}
