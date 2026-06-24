// src/data/api/deleteRule.js
import apiFetch from '@wordpress/api-fetch';
import { clearRulesCache } from './getRules';

const deleteRule = async (id) => {
    try {
        const result = await apiFetch({
            path: `/cos-pro/v1/rules/${id}`,
            method: 'DELETE',
        });
        clearRulesCache();
        return result;
    } catch (error) {
        throw error;
    }
};

export default deleteRule;
