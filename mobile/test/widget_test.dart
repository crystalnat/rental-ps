// Basic Flutter widget test for Kasir POS app.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:mobile/main.dart';
import 'package:mobile/services/storage_service.dart';

void main() {
  testWidgets('App loads and shows login when not authenticated', (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final storage = StorageService(prefs);

    await tester.pumpWidget(MyApp(storage: storage));
    await tester.pumpAndSettle();

    expect(find.text('Kasir POS'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
  });
}
