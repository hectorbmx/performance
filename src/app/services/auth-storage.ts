import { environment } from 'src/environments/environment';

// Credentials and identity belong to one API, even when the WebView origin stays the same.
export const AUTH_API_SCOPE = environment.apiUrl.replace(/\/+$/, '');
export const authStorageKey = (key: string): string => `${key}:${encodeURIComponent(AUTH_API_SCOPE)}`;
