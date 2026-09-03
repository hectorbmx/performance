/// <reference types="@capacitor-firebase/messaging" />

import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.performanceCoachBarret.app',
  appName: 'Coach',
  webDir: 'www',
  experimental: {
    ios: {
      spm: {
        packageOptions: {
          '@capacitor-firebase/messaging': {
            symlink: true,
          },
        },
      },
    },
  },
  server: {
    // Esto ayuda a que el origen sea consistente
    hostname: 'localhost',
    iosScheme: 'capacitor', 
    allowNavigation: [
      'bmxmexico.com'
    ],
  },
  plugins: {
    CapacitorHttp: {
      enabled: true,
    },
    FirebaseMessaging: {
      presentationOptions: ['alert', 'badge', 'sound'],
    },
  },
};

export default config;
