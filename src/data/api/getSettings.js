// src/data/api/getSettings.js
import apiFetch from '@wordpress/api-fetch';

let settingsCache = null;

const getSettings = async () => {
    try {
        const response = await apiFetch({ path: '/cos-pro/v1/settings' });
        settingsCache = response?.data ?? {};
        return settingsCache;
    } catch (error) {
        return {};
    }
};

export const clearSettingsCache = () => {
    settingsCache = null;
};

export default getSettings;