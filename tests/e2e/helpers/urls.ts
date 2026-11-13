import { TENANT_SLUG } from './credentials.js';

export function tenantPath(path: string, locale: 'en' | 'ar'): string {
    const base = path.startsWith('/') ? path : `/${path}`;
    const join = base.includes('?') ? '&' : '?';
    return `/app/${TENANT_SLUG}${base}${join}locale=${locale}`;
}

export function storefrontPath(path: string, locale: 'en' | 'ar'): string {
    const base = path.startsWith('/') ? path : `/${path}`;
    const join = base.includes('?') ? '&' : '?';
    return `/${TENANT_SLUG}${base}${join}locale=${locale}`;
}
