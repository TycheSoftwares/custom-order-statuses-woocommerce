// src/data/api/updateSettings.js
import apiFetch from '@wordpress/api-fetch';
import { clearSettingsCache } from './getSettings';

const updateSettings = async (settingsData) => {
    try {
        const response = await apiFetch({
            path: '/cos-pro/v1/settings',
            method: 'POST',
            data: { settings: settingsData },
        });
        clearSettingsCache();
        return response?.data ?? null;
    } catch (error) {
        throw error;
    }
};

export default updateSettings;