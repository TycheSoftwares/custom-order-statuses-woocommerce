import apiFetch from '@wordpress/api-fetch';

let cache = null;

const getOptions = async () => {
    if ( cache !== null ) {
        return cache;
    }
    try {
        const response = await apiFetch( { path: '/cos-pro/v1/options' } );
        cache = response?.data ?? {};
        return cache;
    } catch ( error ) {
        return {};
    }
};

export const clearOptionsCache = () => { cache = null; };
export default getOptions;
