// File generated for Firebase initialization across platforms.
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      return web;
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      case TargetPlatform.macOS:
        return ios;
      case TargetPlatform.windows:
        return windows;
      case TargetPlatform.linux:
        throw UnsupportedError(
          'DefaultFirebaseOptions have not been configured for linux.',
        );
      default:
        return android;
    }
  }

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'AIzaSyDC6N4BxRB-T051Lzx4kk9jYK0ysiYGxpc',
    appId: '1:293820686302:web:gymsaasweb',
    messagingSenderId: '293820686302',
    projectId: 'gymsaas-dc468',
    authDomain: 'gymsaas-dc468.firebaseapp.com',
    storageBucket: 'gymsaas-dc468.firebasestorage.app',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyDC6N4BxRB-T051Lzx4kk9jYK0ysiYGxpc',
    appId: '1:293820686302:android:55c1f153e217860e8dc74f',
    messagingSenderId: '293820686302',
    projectId: 'gymsaas-dc468',
    storageBucket: 'gymsaas-dc468.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDC6N4BxRB-T051Lzx4kk9jYK0ysiYGxpc',
    appId: '1:293820686302:ios:55c1f153e217860e8dc74f',
    messagingSenderId: '293820686302',
    projectId: 'gymsaas-dc468',
    storageBucket: 'gymsaas-dc468.firebasestorage.app',
    iosBundleId: 'com.fitisify.memberapp.gymMemberApp',
  );

  static const FirebaseOptions windows = FirebaseOptions(
    apiKey: 'AIzaSyDC6N4BxRB-T051Lzx4kk9jYK0ysiYGxpc',
    appId: '1:293820686302:web:55c1f153e217860e8dc74f',
    messagingSenderId: '293820686302',
    projectId: 'gymsaas-dc468',
    storageBucket: 'gymsaas-dc468.firebasestorage.app',
  );
}
