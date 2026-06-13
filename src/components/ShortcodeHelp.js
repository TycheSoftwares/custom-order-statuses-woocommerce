/**
 * src/components/ShortcodeHelp.js
 * Small inline shortcode reference shown in help text.
 */
import { __ } from '@wordpress/i18n';
import { __experimentalText as Text } from '@wordpress/components';

export default function ShortcodeHelp( { codes = [] } ) {
    if ( ! codes.length ) return null;
    return (
        <Text variant="muted" size="small">
            { __( 'Available shortcodes:', 'custom-order-statuses-woocommerce' ) }{ ' ' }
            { codes.map( ( code ) => (
                <code key={ code } className="cos-code-tag">{ `{${ code }}` }</code>
            ) ) }
        </Text>
    );
}
