import apiFetch from '@wordpress/api-fetch';
import { clearStatusesCache } from './getStatuses';
import { clearOptionsCache } from './getOptions';

const saveStatus = async ( statusData, id = null ) => {
    try {
        const response = await apiFetch( {
            path  : id ? `/cos-pro/v1/statuses/${ id }` : '/cos-pro/v1/statuses',
            method: id ? 'PUT' : 'POST',
            data  : statusData,
        } );
        clearStatusesCache();
        clearOptionsCache();
        return response?.data ?? null;
    } catch ( error ) {
        throw error;
    }
};

export default saveStatus;
