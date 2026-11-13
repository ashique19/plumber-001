export type ViewProfile = {
    name: string;
    locale: 'en' | 'ar';
    width: number;
    height: number;
};

export const VIEW_PROFILES: ViewProfile[] = [
    { name: 'en-375', locale: 'en', width: 375, height: 812 },
    { name: 'en-1280', locale: 'en', width: 1280, height: 900 },
    { name: 'ar-375', locale: 'ar', width: 375, height: 812 },
    { name: 'ar-1280', locale: 'ar', width: 1280, height: 900 },
];
