// src/components/ProNotice.js
import { Notice, Button } from '@wordpress/components';
import { ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const UPGRADE_URL = 'https://www.tychesoftwares.com/products/custom-order-statuses-woocommerce-pro/?utm_source=coslite&utm_medium=notice&utm_campaign=upgrade';

// Default export: full‑width notice (used above cards)
export default function ProNotice({ feature }) {
    return (
        <div style={{ display: 'inline-block', maxWidth: '100%', marginBottom: '16px' }}>
            <Notice status="warning" isDismissible={false}>
                <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className="dashicons dashicons-info-outline" style={{ fontSize: '20px', color: '#dba617' }} />
                        <span>
                            {feature && <strong style={{ marginRight: '4px' }}>{feature}</strong>}
                            {__('is only available in the Pro version.', 'custom-order-statuses-woocommerce')}
                        </span>
                    </div>
                    <Button variant="primary" href={UPGRADE_URL} target="_blank" rel="noreferrer">
                        {__('Upgrade to Pro', 'custom-order-statuses-woocommerce')}
                        <span className="dashicons dashicons-external" style={{ fontSize: '16px', marginLeft: '6px', verticalAlign: 'middle' }} />
                    </Button>
                </div>
            </Notice>
        </div>
    );
}

export function ProInlineNotice({ feature, message, className = '' }) {
    const defaultMessage = feature
        ? sprintf( __('%s is only available in the Pro version.', 'custom-order-statuses-woocommerce'), feature )
        : __('This option is only available in the Pro version.', 'custom-order-statuses-woocommerce');

    const displayMessage = message || defaultMessage;

    const style = {
        display      : 'inline-flex',      // always inline
        alignItems   : 'center',
        gap          : '6px',
        marginTop    : 0,
        fontSize     : '12px',
    };

    return (
        <div style={ style } className={ className }>
            <ExternalLink
                href={ UPGRADE_URL }
                style={{
                    textDecoration: 'none',
                    fontWeight: 600
                }}
            >
                <span style={{ textDecoration: 'underline' }}>
                    { __( 'Upgrade to Pro', 'custom-order-statuses-woocommerce' ) }
                </span>
            </ExternalLink>
        </div>
    );
}