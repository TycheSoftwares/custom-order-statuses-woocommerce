// src/data/api/getStatuses.js
import apiFetch from '@wordpress/api-fetch';

let statusesCache = null;

const getStatuses = async () => {
    if ( statusesCache !== null ) {
        return statusesCache;
    }
    try {
        const response = await apiFetch({ path: '/cos-pro/v1/statuses' });
        statusesCache = response?.data ?? [];
        return statusesCache;
    } catch (error) {
        return [];
    }
};

export const clearStatusesCache = () => {
    statusesCache = null;
};

export default getStatuses;