import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'io.ionic.starter',
  appName: 'Coach',
  webDir: 'www',
     server: {
    //  url: 'http://10.0.2.2:8100',
    androidScheme: 'http',
     cleartext: true
   }
};

export default config;
