import { Component } from '@angular/core';
import { Location } from '@angular/common';
import { IonContent, IonHeader, IonToolbar, IonTitle } from '@ionic/angular/standalone';
import { environment } from '../../../environments/environment';

@Component({standalone: true, selector: 'app-account-privacy', imports: [IonContent,IonHeader,IonToolbar,IonTitle],
  styleUrls: ['./account.page.scss'], template: `
  <ion-header><ion-toolbar><ion-title>Privacidad</ion-title></ion-toolbar></ion-header>
  <ion-content class="account-content"><main class="account-container">
    <button class="secondary" (click)="back()">Volver</button>
    <article class="account-card"><h1>Tu cuenta y tus datos</h1><p>Versión: 15 de noviembre de 2026.</p>
      <p>Coach SaaS utiliza tu nombre y correo para tu cuenta de atleta, confirmar tu identidad y recuperar el acceso. Tu contraseña se guarda mediante un hash; no se guarda en texto legible.</p>
      <p>El registro requiere confirmar tu correo y aceptar la política de privacidad. El apellido es opcional. No necesitas tener coach para crear tu cuenta personal.</p>
      <p>Los perfiles, registros de entrenamiento y datos de salud que decidas proporcionar se usan para prestar las funciones correspondientes. Los permisos de salud y las notificaciones se gestionan por separado.</p>
      <p>Los servicios de coach, cuando los contratas, tienen datos comerciales y entrenamientos asignados separados del perfil personal. Crear una cuenta no vincula automáticamente tu correo con la ficha de otro usuario.</p>
      <h2>Conservación y eliminación</h2>
      <p>Puedes iniciar la eliminación de tu cuenta y datos personales desde Mi cuenta → Eliminar mi cuenta. Se requiere tu contraseña para confirmar. El acceso se cierra al aceptar la solicitud; la eliminación se completa en un máximo de 7 días, con confirmación por correo. Si existe información que deba conservarse por obligación legal, se te informará de su alcance.</p>
      <p>Eliminar la cuenta no cancela automáticamente suscripciones con Apple u otros proveedores. Puedes administrarlas con el proveedor correspondiente.</p>
      <p>Las copias de sesiones en otros dispositivos deben eliminarse también de esos dispositivos. No se enviarán nuevos registros con una cuenta cuyo acceso fue revocado.</p>
      <h2>Información completa y contacto</h2>
      <p>Consulta en la política completa las finalidades, proveedores, permisos y derechos. Contacto: soporte&#64;bmxmexico.com.</p>
      <a [href]="policyUrl" target="_blank" rel="noopener noreferrer">Leer la política de privacidad completa</a>
    </article></main></ion-content>`})
export class PrivacyPage {
  readonly policyUrl = environment.apiUrl.replace(/\/api\/v1\/?$/, '') + '/privacy-policy';
  constructor(private location: Location) {}
  back(): void { this.location.back(); }
}
