import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:gym_member_app/core/theme/theme_provider.dart';
import 'package:gym_member_app/providers/auth_provider.dart';
import 'package:gym_member_app/providers/dashboard_provider.dart';
import 'package:gym_member_app/providers/member_data_provider.dart';
import 'package:gym_member_app/screens/gym_lookup_screen.dart';
import 'package:gym_member_app/widgets/branded_button.dart';
import 'package:gym_member_app/widgets/status_badge.dart';

void main() {
  testWidgets('BrandedButton and StatusBadge render correctly', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              BrandedButton(text: 'Verify Gym Code'),
              StatusBadge(status: 'Active'),
              StatusBadge(status: 'Expired'),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Verify Gym Code'), findsOneWidget);
    expect(find.text('ACTIVE'), findsOneWidget);
    expect(find.text('EXPIRED'), findsOneWidget);
  });

  testWidgets('Find Your Gym screen renders core layout elements', (WidgetTester tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ThemeProvider()),
          ChangeNotifierProvider(create: (_) => AuthProvider()),
          ChangeNotifierProvider(create: (_) => DashboardProvider()),
          ChangeNotifierProvider(create: (_) => MemberDataProvider()),
        ],
        child: const MaterialApp(
          home: GymLookupScreen(),
        ),
      ),
    );

    await tester.pump();

    // Verify Title & Inputs
    expect(find.text('Find Your Gym'), findsOneWidget);
    expect(find.byIcon(Icons.fitness_center_rounded), findsWidgets);
    expect(find.text('Gym A (GYM-A)'), findsOneWidget);
    expect(find.text('Gym B (GYM-B)'), findsOneWidget);
  });
}
