import { Injectable } from '@angular/core';
import {
  AndroidBiometryStrength,
  BiometricAuth,
  BiometryError,
  BiometryErrorType,
  BiometryType,
  type CheckBiometryResult,
} from '@aparajita/capacitor-biometric-auth';
import {
  KeychainAccess,
  SecureStorage,
} from '@aparajita/capacitor-secure-storage';
import type { ActorType } from './auth.service';

export interface StoredBiometricSession {
  token: string;
  actorType: ActorType;
  email?: string;
  savedAt: string;
}

export interface BiometricAvailability {
  available: boolean;
  deviceIsSecure: boolean;
  strongAvailable: boolean;
  label: string;
  reason: string | null;
  raw: CheckBiometryResult;
}

export interface BiometricPromptResult {
  ok: boolean;
  cancelled: boolean;
  code: BiometryErrorType | null;
  message: string | null;
}

@Injectable({ providedIn: 'root' })
export class BiometricAuthService {
  private readonly keyPrefix = 'coach_saas_biometric_';
  private readonly enabledKey = 'enabled';
  private readonly sessionKey = 'session';
  private initialized = false;

  async availability(): Promise<BiometricAvailability> {
    await this.initializeStorage();

    const info = await BiometricAuth.checkBiometry();

    return {
      available: info.isAvailable,
      deviceIsSecure: info.deviceIsSecure,
      strongAvailable: info.strongBiometryIsAvailable,
      label: this.labelFor(info.biometryType),
      reason: info.reason || null,
      raw: info,
    };
  }

  async authenticate(reason = 'Confirma que eres tu para entrar.'): Promise<BiometricPromptResult> {
    try {
      await BiometricAuth.authenticate({
        reason,
        cancelTitle: 'Cancelar',
        allowDeviceCredential: false,
        iosFallbackTitle: '',
        androidTitle: 'Entrar con biometria',
        androidSubtitle: 'Confirma tu identidad para desbloquear la sesion.',
        androidConfirmationRequired: false,
        androidBiometryStrength: AndroidBiometryStrength.weak,
      });

      return {
        ok: true,
        cancelled: false,
        code: null,
        message: null,
      };
    } catch (error) {
      if (error instanceof BiometryError) {
        return {
          ok: false,
          cancelled: this.isUserCancellation(error.code),
          code: error.code,
          message: error.message,
        };
      }

      return {
        ok: false,
        cancelled: false,
        code: null,
        message: error instanceof Error ? error.message : 'No se pudo autenticar con biometria.',
      };
    }
  }

  async enableSession(session: StoredBiometricSession): Promise<void> {
    await this.initializeStorage();
    await SecureStorage.setItem(this.sessionKey, JSON.stringify(session));
    await SecureStorage.setItem(this.enabledKey, '1');
  }

  async isEnabled(): Promise<boolean> {
    await this.initializeStorage();
    return (await SecureStorage.getItem(this.enabledKey)) === '1';
  }

  async getStoredSession(): Promise<StoredBiometricSession | null> {
    await this.initializeStorage();

    if (!(await this.isEnabled())) {
      return null;
    }

    const value = await SecureStorage.getItem(this.sessionKey);
    if (!value) {
      return null;
    }

    try {
      const parsed = JSON.parse(value) as StoredBiometricSession;
      return parsed.token && parsed.actorType ? parsed : null;
    } catch {
      await this.clearSession();
      return null;
    }
  }

  async clearSession(): Promise<void> {
    await this.initializeStorage();
    await Promise.all([
      SecureStorage.removeItem(this.sessionKey),
      SecureStorage.removeItem(this.enabledKey),
    ]);
  }

  private async initializeStorage(): Promise<void> {
    if (this.initialized) {
      return;
    }

    await SecureStorage.setKeyPrefix(this.keyPrefix);
    await SecureStorage.setSynchronize(false);
    await SecureStorage.setDefaultKeychainAccess(KeychainAccess.whenPasscodeSetThisDeviceOnly);
    this.initialized = true;
  }

  private isUserCancellation(code: BiometryErrorType): boolean {
    return [
      BiometryErrorType.appCancel,
      BiometryErrorType.systemCancel,
      BiometryErrorType.userCancel,
      BiometryErrorType.userFallback,
    ].includes(code);
  }

  private labelFor(type: BiometryType): string {
    switch (type) {
      case BiometryType.faceId:
      case BiometryType.faceAuthentication:
        return 'Face ID';
      case BiometryType.touchId:
      case BiometryType.fingerprintAuthentication:
        return 'huella';
      case BiometryType.irisAuthentication:
        return 'iris';
      default:
        return 'biometria';
    }
  }
}
