/**
 * src/components/HelpTip.js
 * Alternative version using WordPress Dashicons
 */

import { Tooltip } from '@wordpress/components';

const HelpTip = ({ message, position = 'bottom', className = '' }) => {
    if (!message) {
        return null;
    }

    return (
        <Tooltip text={message} position={position}>
            <span 
                className={`dashicons dashicons-editor-help cos-help-tip ${className}`}
                style={{ 
                    cursor: 'help',
                    color: '#787c82',
                    fontSize: '1.2em',
                    width: '16px',
                    height: '16px',
                    marginRight: '8px'
                }}
            />
        </Tooltip>
    );
};

export default HelpTip;