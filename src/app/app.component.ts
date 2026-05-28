import { Component, OnInit } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { Capacitor } from '@capacitor/core';
import { PushNotifications, PermissionStatus, Token, PushNotificationSchema, ActionPerformed } from '@capacitor/push-notifications';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  imports: [IonApp, IonRouterOutlet],
})
export class AppComponent implements OnInit { // Añade implements OnInit por buena práctica
  constructor() {}

  ngOnInit() {
    this.initPush();
  }

  async initPush() {
    try {
      if (!Capacitor.isNativePlatform()) {
        return;
      }

      // 1) Pedir permisos
      let permStatus: PermissionStatus = await PushNotifications.checkPermissions();

      if (permStatus.receive !== 'granted') {
        permStatus = await PushNotifications.requestPermissions();
      }

      if (permStatus.receive !== 'granted') {
        console.log('Push permission NOT granted');
        return;
      }

      // 2) Registrar con FCM
      await PushNotifications.register();

      // 3) Listener: Registro exitoso (Obtener token)
      PushNotifications.addListener('registration', (token: Token) => {
        console.log('🔥 FCM TOKEN >>>', token.value);
        // TIP: Aquí es donde deberías enviar el token a tu API de Laravel
      });

      // 4) Listener: Error de registro
      PushNotifications.addListener('registrationError', (error) => {
        console.error('❌ FCM registration error:', error);
      });

      // 5) Listener: NOTIFICACIÓN RECIBIDA (App abierta)
      // Este es el que te falta para ver algo en el emulador ahora mismo
      PushNotifications.addListener('pushNotificationReceived', (notification: PushNotificationSchema) => {
        console.log('🔔 Notificación recibida:', notification);
        // Esto lanzará un alert nativo para que confirmes que llegó
        alert(`${notification.title}\n${notification.body}`);
      });

      // 6) Listener: ACCIÓN REALIZADA (Usuario toca la notificación)
      PushNotifications.addListener('pushNotificationActionPerformed', (notification: ActionPerformed) => {
        console.log('🖱️ Acción realizada:', notification.actionId, notification.notification);
      });

    } catch (err) {
      console.error('❌ Push init error:', err);
    }
  }
}
