// src/data/api/getRules.js
import apiFetch from '@wordpress/api-fetch';

let rulesCache = null;

const getRules = async () => {
    if ( rulesCache !== null ) {
        return rulesCache;
    }
    try {
        const response = await apiFetch({ path: '/cos-pro/v1/rules' });
        rulesCache = response?.data ?? [];
        return rulesCache;
    } catch (error) {
        return [];
    }
};

export const clearRulesCache = () => {
    rulesCache = null;
};

export default getRules;