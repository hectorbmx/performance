import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonButton, IonIcon, IonList, IonItem, IonLabel, IonAvatar, IonChip, IonFooter } from '@ionic/angular/standalone';
import { ApiService } from '../services/api.service';
import { AuthService } from '../services/auth.service';
import { firstValueFrom } from 'rxjs';
import { Router } from '@angular/router';
import { environment } from 'src/environments/environment';
// import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { addIcons } from 'ionicons';
import {
  notificationsOutline,
  timeOutline,checkmarkCircle,settingsOutline,starOutline,
  barbellOutline,
  play, walkOutline, flameOutline, flashOutline, calendarOutline, statsChartOutline, personOutline } from 'ionicons/icons';

type ProfileVM = {
  fullName: string;
  roleLabel: string;        // ATHLETE / COACH
  memberSinceLabel: string; // Member since Jan 2023
  photoUrl?: string | null;

  stats: {
    workouts: number;
    dayStreak: number;
    volumeLabel: string;    // "450K"
  };
};
type ProfileResponse = {
  ok: boolean;
  profile?: {
    client: {
      id: number;
      first_name: string | null;
      last_name: string | null;
      full_name: string;
      email: string;
      phone: string | null;
      avatar_url: string | null;
      is_active: boolean;
    };
    stats?: {
      workouts: number;
      day_streak: number;
      volume: number;
    };
  };
  message?: string;
};

@Component({
  selector: 'app-tab3',
  templateUrl: 'tab3.page.html',
  styleUrls: ['tab3.page.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonHeader, IonToolbar, IonTitle, IonContent,
    IonButtons, IonBackButton, IonButton, IonIcon,
    IonList, IonItem, IonLabel, IonAvatar, IonChip,
    IonFooter
  ],
})
export class Tab3Page {
  loading = false;

  vm: ProfileVM = {
    fullName: '—',
    roleLabel: 'ATHLETE',
    memberSinceLabel: 'Member since —',
    photoUrl: null,
    stats: { workouts: 0, dayStreak: 0, volumeLabel: '0' }
  };

  constructor(
    private api: ApiService,
    private router: Router,
    private auth: AuthService
  ) {
        addIcons({settingsOutline,checkmarkCircle,barbellOutline,flameOutline,statsChartOutline,personOutline,starOutline,notificationsOutline,timeOutline,play,walkOutline,flashOutline,calendarOutline,});

  }

  ionViewWillEnter() {
    this.loadMe();
  }

  async loadMe() {
    this.loading = true;
    try {
      // const res: any = await firstValueFrom(this.api.me());
      const res: any = await this.auth.me();
            
      const first = res?.client?.first_name ?? '';
      const last  = res?.client?.last_name ?? '';
      const fullName = `${first} ${last}`.trim() || (res?.user?.email ?? '—');
      // Si quieres derivar el rol por presencia de coach_id, ajústalo a tu lógica real.
      const roleLabel = res?.client?.coach_id ? 'ATHLETE' : 'ATHLETE';
      // Si aún no tienes fecha real, lo dejamos "simple" (puedes mapearlo luego desde DB)
      const memberSinceLabel = 'Member since Jan 2023';
      // Foto: si aún no existe, queda null y mostramos fallback
      const raw = res?.client?.avatar_url ?? null;
      const photoUrl = raw ? raw.replace(/\\/g, '/') : null;

      // Stats: por ahora hardcode “con vida”. Luego los conectas a endpoints reales.
      this.vm = {
        fullName,
        roleLabel,
        memberSinceLabel,
        photoUrl,
        stats: {
          workouts: 128,
          dayStreak: 15,
          volumeLabel: '450K'
        }
      };
    } catch (e) {
      // Puedes mostrar toast si quieres.
      // Mantengo el VM básico para no romper UI.
      console.error('me() failed', e);
    } finally {
      this.loading = false;
    }
  }

  goSettings() {
    // Ajusta a tu ruta real
    this.router.navigate(['/settings']);
  }

  goPersonalInfo() {
    // Ajusta a tu ruta real
    this.router.navigate(['user-profile']);
  }

  goAchievements() {
    // Ajusta a tu ruta real
    this.router.navigate(['/profile/achievements']);
  }

  goSubscription() {
    // Ajusta a tu ruta real
    this.router.navigate(['/subscription-history']);
  }

  goHelpCenter() {
    // Ajusta a tu ruta real
    this.router.navigate(['/help-center']);
  }

  async logout() {
    await this.auth.logout();

    // Redirección final
    this.router.navigate(['/login'], { replaceUrl: true });
  }
//   private async fileFromWebPath(webPath: string, filename: string): Promise<File> {
//   const resp = await fetch(webPath);
//   const blob = await resp.blob();
//   return new File([blob], filename, { type: blob.type || 'image/jpeg' });
// }
async changeAvatar() {
  try {
    // Web fallback (ionic serve)
    const isNative = !!(window as any).Capacitor?.isNativePlatform?.();
    if (!isNative) {
      await this.pickAvatarFromBrowser();
      return;
    }

    // Native prompt (camera/gallery)
    const photo = await Camera.getPhoto({
      quality: 75,
      resultType: CameraResultType.Uri,
      source: CameraSource.Prompt,
      allowEditing: false,
      correctOrientation: true,
      saveToGallery: false,
    });

    if (!photo.webPath) return;

    const file = await this.fileFromWebPath(photo.webPath, `avatar_${Date.now()}.jpg`);
    await this.uploadAvatar(file);
  } catch (e) {
    console.error('changeAvatar failed', e);
  }
}

private async pickAvatarFromBrowser() {
  return new Promise<void>((resolve) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
      const file = input.files?.[0];
      if (file) {
        await this.uploadAvatar(file);
      }
      resolve();
    };
    input.click();
  });
}

private async uploadAvatar(file: File) {
  const formData = new FormData();
  formData.append('avatar', file);

  const res: any = await this.api.postFormData('client/profile/avatar', formData);

  if (res?.ok && res?.avatar?.avatar_url) {
    this.vm = { ...this.vm, photoUrl: res.avatar.avatar_url };
  } else {
    console.error('uploadAvatar response', res);
  }
}

private async fileFromWebPath(webPath: string, filename: string): Promise<File> {
  const resp = await fetch(webPath);
  const blob = await resp.blob();
  return new File([blob], filename, { type: blob.type || 'image/jpeg' });
}

testClick() {
  console.log('avatar click ok');
}

}
