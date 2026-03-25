import 'dart:async';

import 'package:flutter/material.dart';

/// Toast ringan di **kanan atas** (bukan SnackBar bawah) — dipakai app-wide.
class AppToast {
  AppToast._();

  static OverlayEntry? _entry;
  static Timer? _timer;

  static OverlayState? _overlay(BuildContext context) {
    final o = Overlay.maybeOf(context);
    if (o != null) return o;
    return Navigator.maybeOf(context, rootNavigator: true)?.overlay;
  }

  /// Tampilkan pesan di kanan atas. Toast baru menggantikan yang sebelumnya.
  static void show(
    BuildContext context,
    String message, {
    Duration duration = const Duration(seconds: 3),
    Color? backgroundColor,
  }) {
    _timer?.cancel();
    _entry?.remove();
    _entry = null;

    final overlay = _overlay(context);
    if (overlay == null) return;

    final theme = Theme.of(context);
    final bg = backgroundColor ?? theme.colorScheme.inverseSurface;
    final fg = ThemeData.estimateBrightnessForColor(bg) == Brightness.dark
        ? Colors.white
        : theme.colorScheme.onSurface;

    _entry = OverlayEntry(
      builder: (ctx) => Positioned(
        top: MediaQuery.paddingOf(ctx).top + 8,
        right: 12,
        child: Material(
          elevation: 6,
          shadowColor: Colors.black26,
          borderRadius: BorderRadius.circular(10),
          color: bg,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(ctx).width * 0.78,
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
              child: Text(
                message,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: fg,
                  fontWeight: FontWeight.w500,
                  height: 1.35,
                ),
              ),
            ),
          ),
        ),
      ),
    );

    overlay.insert(_entry!);
    _timer = Timer(duration, () {
      _entry?.remove();
      _entry = null;
      _timer = null;
    });
  }
}
