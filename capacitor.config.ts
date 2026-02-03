import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'io.ionic.starter',
  appName: 'Coach',
  webDir: 'www',
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
}
};

export default config;