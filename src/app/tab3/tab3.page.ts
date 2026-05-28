import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonContent, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonIcon, IonList, IonItem, IonLabel, IonAvatar, IonChip, IonLoading } from '@ionic/angular/standalone';
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
  memberSinceLabel: string;
  photoUrl?: string | null;

  stats: {
    workouts: number;
    dayStreak: number;
    volumeLabel: string;    // "450K"
  } | null;
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
    IonButtons, IonBackButton, IonIcon,
    IonList, IonItem, IonLabel, IonAvatar, IonChip, IonLoading,
  ],
})
export class Tab3Page {
  loading = false;
  isUploadingAvatar = false;
  vm: ProfileVM = {
    fullName: '—',
    roleLabel: 'ATHLETE',
    memberSinceLabel: 'Member since —',
    photoUrl: null,
    stats: null
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
      const memberSinceLabel = res?.membership?.starts_at
        ? `Membresia desde ${this.formatDate(res.membership.starts_at)}`
        : 'Membresia activa';
      // Foto: si aún no existe, queda null y mostramos fallback
      const raw = res?.client?.avatar_url ?? null;
      const photoUrl = raw ? raw.replace(/\\/g, '/') : null;

      // Stats: por ahora hardcode “con vida”. Luego los conectas a endpoints reales.
      this.vm = {
        fullName,
        roleLabel,
        memberSinceLabel,
        photoUrl,
        stats: res?.stats ? {
          workouts: Number(res.stats.workouts ?? 0),
          dayStreak: Number(res.stats.day_streak ?? 0),
          volumeLabel: this.formatVolume(Number(res.stats.volume ?? 0))
        } : null
      };
    } catch (e) {
      // Puedes mostrar toast si quieres.
      // Mantengo el VM básico para no romper UI.
      console.error('me() failed', e);
    } finally {
      this.loading = false;
    }
  }

  private formatDate(value: string): string {
    const date = this.parseDate(value);
    if (!date) return '';

    return date.toLocaleDateString('es-MX', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private parseDate(value: string | null | undefined): Date | null {
    if (!value) return null;

    const raw = String(value);
    const date = /^\d{4}-\d{2}-\d{2}$/.test(raw)
      ? new Date(`${raw}T00:00:00`)
      : new Date(raw);

    return Number.isNaN(date.getTime()) ? null : date;
  }

  private formatVolume(value: number): string {
    if (value >= 1000) return `${Math.round(value / 1000)}K`;
    return String(value);
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
// async changeAvatar() {
//   try {
//     // Web fallback (ionic serve)
//     const isNative = !!(window as any).Capacitor?.isNativePlatform?.();
//     if (!isNative) {
//       await this.pickAvatarFromBrowser();
//       return;
//     }

//     // Native prompt (camera/gallery)
//     const photo = await Camera.getPhoto({
//       quality: 75,
//       resultType: CameraResultType.Uri,
//       source: CameraSource.Prompt,
//       allowEditing: false,
//       correctOrientation: true,
//       saveToGallery: false,
//     });

//     if (!photo.webPath) return;

//     const file = await this.fileFromWebPath(photo.webPath, `avatar_${Date.now()}.jpg`);
//     await this.uploadAvatar(file);
//   } catch (e) {
//     console.error('changeAvatar failed', e);
//   }
// }

async changeAvatar() {
  try {
    const isNative = !!(window as any).Capacitor?.isNativePlatform?.();
    
    if (!isNative) {
      // Nota: Asegúrate de que pickAvatarFromBrowser también maneje el loader internamente
      // o envuélvelo aquí si es necesario.
      await this.pickAvatarFromBrowser();
      return;
    }

    const photo = await Camera.getPhoto({
      quality: 75,
      resultType: CameraResultType.Uri,
      source: CameraSource.Prompt,
      allowEditing: false,
      correctOrientation: true,
      saveToGallery: false,
    });

    if (!photo.webPath) return;

    // Activamos el loader justo antes de procesar y subir
    this.isUploadingAvatar = true;

    const file = await this.fileFromWebPath(photo.webPath, `avatar_${Date.now()}.jpg`);
    await this.uploadAvatar(file);

  } catch (e) {
    console.error('changeAvatar failed', e);
  } finally {
    // Apagamos el loader pase lo que pase
    this.isUploadingAvatar = false;
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
        this.isUploadingAvatar = true;
        try {
          await this.uploadAvatar(file);
        } finally {
          this.isUploadingAvatar = false;
        }
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
    await this.auth.me();
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
