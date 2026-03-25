import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'providers/auth_provider.dart';
import 'screens/expenses_screen.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/order_history_screen.dart';
import 'screens/pending_orders_screen.dart';
import 'screens/store_selector_screen.dart';
import 'screens/transaksi_screen.dart';
import 'services/storage_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  final storage = StorageService(prefs);

  runApp(MyApp(storage: storage));
}

class MyApp extends StatelessWidget {
  final StorageService storage;

  const MyApp({super.key, required this.storage});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(storage),
      child: MaterialApp(
        title: 'Kasir POS',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF1976D2),
            brightness: Brightness.light,
          ),
          useMaterial3: true,
        ),
        initialRoute: '/',
        routes: {
          '/': (ctx) => const AuthWrapper(),
          '/login': (ctx) => const LoginScreen(),
          '/store-selector': (ctx) => const StoreSelectorScreen(),
          '/home': (ctx) => const HomeScreen(),
          '/transaksi': (ctx) => const TransaksiScreen(),
          '/pending-orders': (ctx) => const PendingOrdersScreen(),
          '/order-history': (ctx) => const OrderHistoryScreen(),
          '/expenses': (ctx) => const ExpensesScreen(),
        },
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  const AuthWrapper({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (_, auth, __) {
        if (!auth.isAuthenticated) {
          return const LoginScreen();
        }
        // Admin/owner harus pilih toko dulu
        if (auth.needsStoreSelection && !auth.hasStoreContext) {
          return const StoreSelectorScreen();
        }
        return const HomeScreen();
      },
    );
  }
}
