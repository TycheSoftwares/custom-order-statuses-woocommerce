import apiFetch from '@wordpress/api-fetch';
import { clearStatusesCache } from './getStatuses';
import { clearOptionsCache } from './getOptions';

const deleteStatus = async ( id ) => {
    try {
        const result = await apiFetch( {
            path  : `/cos-pro/v1/statuses/${ id }`,
            method: 'DELETE',
        } );
        clearStatusesCache();
        clearOptionsCache();
        return result;
    } catch ( error ) {
        throw error;
    }
};

export default deleteStatus;
