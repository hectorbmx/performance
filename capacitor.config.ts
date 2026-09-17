/// <reference types="@capacitor-firebase/messaging" />

import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.app33performance',
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
      'https://coach.training-flow.com/login'
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
