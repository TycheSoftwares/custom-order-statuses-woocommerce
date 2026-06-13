import apiFetch from '@wordpress/api-fetch';

const deleteStatus = async ( id ) => {
    try {
        return await apiFetch( {
            path  : `/cos-pro/v1/statuses/${ id }`,
            method: 'DELETE',
        } );
    } catch ( error ) {
        throw error;
    }
};

export default deleteStatus;
