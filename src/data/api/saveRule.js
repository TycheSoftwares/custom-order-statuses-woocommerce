// src/data/api/saveRule.js
import apiFetch from '@wordpress/api-fetch';
import { clearRulesCache } from './getRules';

const saveRule = async (rule, id = null) => {
    try {
        const response = await apiFetch({
            path: id ? `/cos-pro/v1/rules/${id}` : '/cos-pro/v1/rules',
            method: id ? 'PUT' : 'POST',
            data: rule,
        });
        // Clear cache since data has changed
        clearRulesCache();
        return response?.data ?? null;
    } catch (error) {
        throw error;
    }
};

export default saveRule;