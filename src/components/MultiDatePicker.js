/**
 * src/components/MultiDatePicker.js
 *
 * A multi-date picker that:
 *   - Displays selected dates as removable chip tags
 *   - Opens a mini calendar to add dates
 *   - Stores/accepts a comma-separated string of MM-DD-YYYY dates
 *     (format the PHP backend expects: 03-24-2026,03-27-2026)
 *   - No extra npm package needed — uses native <input type="date">
 *     for the calendar, converts to MM-DD-YYYY on selection
 *
 * Props:
 *   value    {string}   Comma-separated MM-DD-YYYY string e.g. "03-24-2026,03-27-2026"
 *   onChange {function} Called with updated comma-separated string
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ }                          from '@wordpress/i18n';
import {
    __experimentalText as Text,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
} from '@wordpress/components';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * "YYYY-MM-DD" (native input value) → "MM-DD-YYYY" (stored format)
 */
function isoToStored( iso ) {
    if ( ! iso ) return '';
    const [ y, m, d ] = iso.split( '-' );
    return `${ m }-${ d }-${ y }`;
}

/**
 * "MM-DD-YYYY" (stored) → "YYYY-MM-DD" (native input value)
 */
function storedToIso( stored ) {
    if ( ! stored ) return '';
    const [ m, d, y ] = stored.split( '-' );
    return `${ y }-${ m }-${ d }`;
}

/**
 * "MM-DD-YYYY" → human-readable "Mar 24, 2026"
 */
function formatDisplay( stored ) {
    if ( ! stored ) return stored;
    try {
        const iso  = storedToIso( stored );
        const date = new Date( iso + 'T00:00:00' );
        return date.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } );
    } catch {
        return stored;
    }
}

/**
 * Parse comma-separated string into a clean array of date strings.
 */
function parseDates( value ) {
    if ( ! value ) return [];
    return value
        .split( ',' )
        .map( d => d.trim() )
        .filter( Boolean );
}

/**
 * Serialize array back to comma-separated string.
 */
function serializeDates( arr ) {
    return arr.join( ',' );
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function MultiDatePicker( { value = '', onChange } ) {
    const dates      = parseDates( value );
    const inputRef   = useRef( null );
    const [ inputVal, setInputVal ] = useState( '' );

    // When user picks a date from the native calendar
    const handleDateChange = ( e ) => {
        const iso    = e.target.value;   // "YYYY-MM-DD"
        if ( ! iso ) return;

        // Check if the selected date is in the past
        const selectedDate = new Date(iso);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Reset time to start of day for accurate comparison
        
        if (selectedDate < today) {
            alert(__('Cannot select past dates. Please select today or a future date.', 'custom-order-statuses-woocommerce'));
            e.target.value = '';
            setInputVal('');
            return;
        }
        
        const stored = isoToStored( iso ); // "MM-DD-YYYY"

        if ( ! dates.includes( stored ) ) {
            onChange( serializeDates( [ ...dates, stored ] ) );
        }
        // Reset the native input so the same date can be re-selected if removed
        e.target.value = '';
        setInputVal( '' );
    };

    // Remove a date chip
    const removeDate = ( dateStr ) => {
        onChange( serializeDates( dates.filter( d => d !== dateStr ) ) );
    };

    // Clear all
    const clearAll = () => onChange( '' );

    return (
        <VStack spacing={ 2 }>
            { /* ── Date chips ─────────────────────────────────────────────── */ }
            { dates.length > 0 && (
                <div style={ {
                    display        : 'flex',
                    flexWrap       : 'wrap',
                    gap            : '6px',
                    padding        : '8px',
                    border         : '1px solid #c3c4c7',
                    borderRadius   : '4px',
                    background     : '#fafafa',
                    minHeight      : '40px',
                    alignItems     : 'center',
                } }>
                    { dates.map( ( date ) => (
                        <span
                            key={ date }
                            style={ {
                                display        : 'inline-flex',
                                alignItems     : 'center',
                                gap            : '4px',
                                background     : '#e8f0f9',
                                color          : '#2271b1',
                                border         : '1px solid #b3d0f5',
                                borderRadius   : '3px',
                                padding        : '2px 8px',
                                fontSize       : '12px',
                                fontWeight     : 500,
                                whiteSpace     : 'nowrap',
                            } }
                        >
                            { formatDisplay( date ) }
                            <button
                                type="button"
                                onClick={ () => removeDate( date ) }
                                aria-label={ `Remove ${ date }` }
                                style={ {
                                    background     : 'none',
                                    border         : 'none',
                                    cursor         : 'pointer',
                                    color          : '#2271b1',
                                    fontSize       : '14px',
                                    lineHeight     : 1,
                                    padding        : '0 0 0 2px',
                                    display        : 'flex',
                                    alignItems     : 'center',
                                    opacity        : 0.7,
                                } }
                                onMouseEnter={ e => e.currentTarget.style.opacity = 1 }
                                onMouseLeave={ e => e.currentTarget.style.opacity = 0.7 }
                            >
                                ×
                            </button>
                        </span>
                    ) ) }
                </div>
            ) }

            { /* ── Date input + label ──────────────────────────────────────── */ }
            <HStack spacing={ 2 }>
                <div style={ { position: 'relative', display: 'inline-flex', alignItems: 'center' } }>
                    <input
                        ref={ inputRef }
                        type="date"
                        value={ inputVal }
                        onChange={ handleDateChange }
                        style={ {
                            height       : '36px',
                            padding      : '4px 10px',
                            border       : '1px solid #8c8f94',
                            borderRadius : '4px',
                            fontSize     : '13px',
                            fontFamily   : 'inherit',
                            color        : '#1d2327',
                            cursor       : 'pointer',
                            background   : '#fff',
                            outline      : 'none',
                        } }
                        onFocus={ e => e.target.style.borderColor = '#2271b1' }
                        onBlur={  e => e.target.style.borderColor = '#8c8f94' }
                    />
                </div>

                <Text variant="muted" size="small">
                    { dates.length === 0
                        ? __( 'Click the calendar to add dates to skip.', 'custom-order-statuses-woocommerce' )
                        : `${ dates.length } ${ __( 'date(s) selected', 'custom-order-statuses-woocommerce' ) }`
                    }
                </Text>

                { dates.length > 0 && (
                    <button
                        type="button"
                        onClick={ clearAll }
                        style={ {
                            background     : 'none',
                            border         : 'none',
                            cursor         : 'pointer',
                            color          : '#b32d2e',
                            fontSize       : '12px',
                            padding        : 0,
                            textDecoration : 'underline',
                        } }
                    >
                        { __( 'Clear all', 'custom-order-statuses-woocommerce' ) }
                    </button>
                ) }
            </HStack>

            <Text variant="muted" size="small">
                { __( 'Selected dates shown above. Click × to remove.', 'custom-order-statuses-woocommerce' ) }
            </Text>
        </VStack>
    );
}
