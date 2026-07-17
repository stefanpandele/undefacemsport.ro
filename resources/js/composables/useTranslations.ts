import { usePage } from '@inertiajs/vue3';

type TranslationTree = { [key: string]: string | TranslationTree };

/**
 * Resolves translation keys shared from Laravel's lang files via Inertia.
 * Keys use dot notation and map to nested lang arrays,
 * e.g. `t('club_application.form.club_name.label')`.
 */
export function useTranslations() {
    const page = usePage();

    const t = (key: string): string => {
        const translations = (page.props.translations ?? {}) as TranslationTree;

        const value = key
            .split('.')
            .reduce<string | TranslationTree | undefined>(
                (node, segment) =>
                    node && typeof node === 'object' ? node[segment] : undefined,
                translations,
            );

        return typeof value === 'string' ? value : key;
    };

    return { t };
}
