import apiFetch from '@wordpress/api-fetch';

const searchProducts = async ( search = '' ) => {
    try {
        const response = await apiFetch( {
            path: `/cos-pro/v1/options/products?search=${ encodeURIComponent( search ) }`,
        } );
        return response?.data ?? [];
    } catch ( error ) {
        return [];
    }
};

export default searchProducts;
