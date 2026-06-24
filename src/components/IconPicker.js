/**
 * src/components/IconPicker.js
 *
 * Font Awesome icon picker — uses the plugin's own assets/js/icons.json.
 *
 * JSON structure (per entry):
 *   {
 *     "box": {
 *       "unicode": "f466",
 *       "styles":  ["solid"],
 *       "free":    ["solid"],
 *       "label":   "Box",
 *       "search":  { "terms": ["archive", "package", "parcel"] },
 *       "aliases": { "names": ["..."] }
 *     }
 *   }
 *
 * Key rules derived from the JSON and matching icon-picker.js:
 *   - ONLY show icons where free[] includes "solid" → renders as "fa-solid fa-{name}"
 *   - Brand icons (free[] = ["brands"]) are excluded — they need "fa-brands" class
 *     and are not used as order status icons
 *   - Stored value = unicode hex string (e.g. "f466"), same as old PHP meta field
 *   - Search covers: icon name, label, search.terms[], aliases.names[]
 *
 * Props:
 *   value    {string}   unicode hex e.g. "f466" — empty string = no icon
 *   onChange {function} called with new unicode hex, or "" to remove
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Modal,
    Spinner,
    Button,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalText  as Text,
} from '@wordpress/components';

// ── Module-level cache — one fetch per page load ──────────────────────────────
let _iconsCache    = null;   // filtered solid-only entries: [ [name, {unicode,label,...}] ]
let _loadPromise   = null;

async function loadIcons() {
    if ( _iconsCache ) return _iconsCache;
    if ( _loadPromise ) return _loadPromise;

    _loadPromise = ( async () => {
        const url = window.cosProData?.iconsJsonUrl;
        if ( ! url ) return [];

        const raw  = await fetch( url ).then( r => r.json() );

        // Keep ONLY icons that are free AND solid — these render with fa-solid fa-{name}
        // Brand icons need fa-brands class and are not suitable as order status icons
        _iconsCache = Object.entries( raw ).filter(
            ( [ , v ] ) => Array.isArray( v.free ) && v.free.includes( 'solid' )
        );

        return _iconsCache;
    } )().catch( () => [] );

    return _loadPromise;
}

// ── Exported helpers for table display ───────────────────────────────────────

/**
 * Find icon name (e.g. "box") from stored unicode (e.g. "f466").
 * Returns null if icons not loaded yet or unicode not found.
 */
export function getIconName( unicode ) {
    if ( ! unicode || ! _iconsCache ) return null;
    const entry = _iconsCache.find( ( [ , v ] ) => v.unicode === unicode );
    return entry ? entry[ 0 ] : null;
}

/**
 * Small inline component for rendering a saved icon in a table cell.
 *
 * Triggers icons.json load on mount so the icon renders correctly on
 * page refresh — without needing the picker modal to have been opened first.
 * Uses local state so the component re-renders once the load completes.
 */
export function IconTableCell( { unicode } ) {
    const [ , setLoaded ] = useState( false );

    useEffect( () => {
        if ( _iconsCache ) {
            setLoaded( true );
            return;
        }
        loadIcons().then( () => setLoaded( true ) );
    }, [] );

    if ( ! unicode ) return <span style={ { color: '#999' } }>—</span>;

    const name = getIconName( unicode );

    return (
        <span style={ { display: 'inline-flex', alignItems: 'center', gap: '6px' } }>
            { name
                ? <i className={ `fa-solid fa-${ name }` }
                     style={ { fontSize: '18px', color: '#444', width: '20px', textAlign: 'center' } }
                     aria-hidden="true" />
                : null
            }
        </span>
    );
}

// ── IconPicker component ──────────────────────────────────────────────────────

const PER_PAGE = 80;

export default function IconPicker( { value, onChange } ) {
    const [ isOpen,  setIsOpen  ] = useState( false );
    const [ icons,   setIcons   ] = useState( _iconsCache );  // null until loaded
    const [ loading, setLoading ] = useState( false );
    const [ search,  setSearch  ] = useState( '' );
    const [ page,    setPage    ] = useState( 0 );

    // Load icons when modal first opens
    useEffect( () => {
        if ( ! isOpen ) return;
        if ( _iconsCache ) { setIcons( _iconsCache ); return; }
        setLoading( true );
        loadIcons().then( entries => {
            setIcons( entries );
            setLoading( false );
        } );
    }, [ isOpen ] );

    // Reset search + pagination when modal opens/closes
    useEffect( () => {
        if ( isOpen ) { setSearch( '' ); setPage( 0 ); }
    }, [ isOpen ] );

    // Reset page when search changes
    useEffect( () => { setPage( 0 ); }, [ search ] );

    // ── Filtering ─────────────────────────────────────────────────────────────
    const filtered = ( icons ?? [] ).filter( ( [ name, v ] ) => {
        if ( ! search ) return true;
        const q = search.toLowerCase();

        // 1. Icon key name  e.g. "box-open"
        if ( name.includes( q ) ) return true;

        // 2. Label          e.g. "Box Open"
        if ( ( v.label ?? '' ).toLowerCase().includes( q ) ) return true;

        // 3. search.terms   e.g. ["archive", "package", "parcel"]
        const terms = v.search?.terms ?? [];
        if ( terms.some( t => t.toLowerCase().includes( q ) ) ) return true;

        // 4. aliases.names  e.g. ["innosoft"]
        const aliases = v.aliases?.names ?? [];
        if ( aliases.some( a => a.toLowerCase().includes( q ) ) ) return true;

        return false;
    } );

    const totalPages  = Math.ceil( filtered.length / PER_PAGE );
    const pageEntries = filtered.slice( page * PER_PAGE, ( page + 1 ) * PER_PAGE );

    // ── Current icon display ──────────────────────────────────────────────────
    const currentName = value && icons
        ? ( icons.find( ( [ , v ] ) => v.unicode === value ) ?? [ null ] )[ 0 ]
        : null;

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <>
            { /* Trigger row — icon preview + Add/Remove buttons */ }
            <HStack spacing={ 2 } alignment="left">
                { value && currentName && (
                    <i
                        className={ `fa-solid fa-${ currentName }` }
                        style={ { fontSize: '24px', color: '#444', width: '26px', textAlign: 'center' } }
                        aria-hidden="true"
                        title={ `${ currentName } (${ value })` }
                    />
                ) }

                <Button
                    variant="primary"
                    type="button"
                    onClick={ () => setIsOpen( true ) }
                    style={ { height: 'auto' } }
                >
                    { __( 'Add Icon', 'custom-order-statuses-woocommerce' ) }
                </Button>

                { value && (
                    <Button
                        variant="secondary"
                        type="button"
                        onClick={ () => onChange( '' ) }
                        style={ { height: 'auto' } }
                    >
                        { __( 'Remove Icon', 'custom-order-statuses-woocommerce' ) }
                    </Button>
                ) }
            </HStack>

            { /* Picker modal */ }
            { isOpen && (
                <Modal
                    title={ __( 'Select Icon', 'custom-order-statuses-woocommerce' ) }
                    onRequestClose={ () => setIsOpen( false ) }
                    style={ { maxWidth: '700px', width: '92vw' } }
                >
                    <VStack spacing={ 3 }>

                        { /* Search input */ }
                        <input
                            type="text"
                            value={ search }
                            onChange={ e => setSearch( e.target.value ) }
                            placeholder={ __( 'Search by name, label or keyword…', 'custom-order-statuses-woocommerce' ) }
                            autoFocus
                            style={ {
                                width        : '100%',
                                height       : '36px',
                                padding      : '0 12px',
                                border       : '1px solid #8c8f94',
                                borderRadius : '4px',
                                fontSize     : '13px',
                                fontFamily   : 'inherit',
                                boxSizing    : 'border-box',
                                outline      : 'none',
                            } }
                            onFocus={ e => e.target.style.borderColor = '#2271b1' }
                            onBlur={  e => e.target.style.borderColor = '#8c8f94' }
                        />

                        { /* Loading state */ }
                        { loading && (
                            <div style={ { textAlign: 'center', padding: '48px 0' } }>
                                <Spinner />
                                <br />
                                <Text variant="muted">
                                    { __( 'Loading icons…', 'custom-order-statuses-woocommerce' ) }
                                </Text>
                            </div>
                        ) }

                        { /* Results count + pagination info */ }
                        { ! loading && (
                            <Text variant="muted" size="small">
                                { filtered.length }{ ' ' }
                                { __( 'icons', 'custom-order-statuses-woocommerce' ) }
                                { totalPages > 1 && (
                                    ` · ${ __( 'Page', 'custom-order-statuses-woocommerce' ) } ${ page + 1 } / ${ totalPages }`
                                ) }
                            </Text>
                        ) }

                        { /* Icon grid */ }
                        { ! loading && (
                            <div style={ {
                                display             : 'grid',
                                gridTemplateColumns : 'repeat(auto-fill, minmax(76px, 1fr))',
                                gap                 : '6px',
                                maxHeight           : '400px',
                                overflowY           : 'auto',
                                padding             : '2px',
                            } }>
                                { pageEntries.map( ( [ name, iconData ] ) => {
                                    const isSelected = iconData.unicode === value;
                                    return (
                                        <button
                                            key={ name }
                                            type="button"
                                            title={ `${ name } · ${ iconData.unicode }` }
                                            onClick={ () => {
                                                onChange( iconData.unicode );
                                                setIsOpen( false );
                                            } }
                                            style={ {
                                                display        : 'flex',
                                                flexDirection  : 'column',
                                                alignItems     : 'center',
                                                justifyContent : 'center',
                                                gap            : '5px',
                                                padding        : '10px 4px 8px',
                                                border         : isSelected
                                                    ? '2px solid #2271b1'
                                                    : '1px solid #ddd',
                                                borderRadius   : '4px',
                                                background     : isSelected ? '#e8f0f9' : '#fff',
                                                cursor         : 'pointer',
                                                minHeight      : '68px',
                                                transition     : 'all 0.1s',
                                            } }
                                            onMouseEnter={ e => {
                                                if ( ! isSelected ) {
                                                    e.currentTarget.style.borderColor = '#2271b1';
                                                    e.currentTarget.style.background  = '#f0f6ff';
                                                }
                                            } }
                                            onMouseLeave={ e => {
                                                if ( ! isSelected ) {
                                                    e.currentTarget.style.borderColor = '#ddd';
                                                    e.currentTarget.style.background  = '#fff';
                                                }
                                            } }
                                        >
                                            <i
                                                className={ `fa-solid fa-${ name }` }
                                                style={ {
                                                    fontSize : '22px',
                                                    color    : isSelected ? '#2271b1' : '#555',
                                                    width    : '24px',
                                                    textAlign: 'center',
                                                } }
                                                aria-hidden="true"
                                            />
                                            <span style={ {
                                                fontSize  : '10px',
                                                color     : '#646970',
                                                lineHeight: 1.3,
                                                textAlign : 'center',
                                                wordBreak : 'break-all',
                                                maxWidth  : '72px',
                                            } }>
                                                { name }
                                            </span>
                                        </button>
                                    );
                                } ) }

                                { pageEntries.length === 0 && (
                                    <p style={ {
                                        gridColumn: '1 / -1',
                                        textAlign : 'center',
                                        color     : '#646970',
                                        padding   : '32px 0',
                                        margin    : 0,
                                    } }>
                                        { __( 'No icons found for', 'custom-order-statuses-woocommerce' ) }
                                        { ' "' }{ search }{ '"' }
                                    </p>
                                ) }
                            </div>
                        ) }

                        { /* Pagination */ }
                        { ! loading && totalPages > 1 && (
                            <HStack justify="center" spacing={ 2 } alignment="center">
                                <Button
                                    variant="secondary"
                                    type="button"
                                    isSmall
                                    disabled={ page === 0 }
                                    onClick={ () => setPage( p => p - 1 ) }
                                >
                                    ← { __( 'Prev', 'custom-order-statuses-woocommerce' ) }
                                </Button>
                                <Text>{ page + 1 } / { totalPages }</Text>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    isSmall
                                    disabled={ page >= totalPages - 1 }
                                    onClick={ () => setPage( p => p + 1 ) }
                                >
                                    { __( 'Next', 'custom-order-statuses-woocommerce' ) } →
                                </Button>
                            </HStack>
                        ) }

                    </VStack>
                </Modal>
            ) }
        </>
    );
}
