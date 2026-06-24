/**
 * src/screens/StatusManager.js
 * Free version – core status management is free; advanced features (stock, email, SMS, paid flag, customer cancellation) are Pro-only.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    Modal,
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Spinner,
    withNotices,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import { useForm } from 'react-hook-form';
import SettingsCard from '../components/SettingsCard';
import ProNotice from '../components/ProNotice';
import ShortcodeHelp from '../components/ShortcodeHelp';
import IconPicker, { IconTableCell } from '../components/IconPicker';
import { useSettings } from '../context/SettingsContext';
import { saveStatus, deleteStatus } from '../data/api';

// ── Constants ─────────────────────────────────────────────────────────────────

const EMAIL_SUBJECT_CODES = [
    'order_id','order_number','order_date','site_title','status_from','status_to',
];
const EMAIL_CONTENT_CODES = [
    'order_id','order_number','order_date','order_details','first_name','last_name',
    'billing_address','shipping_address','product_titles','site_title',
    'status_from','status_to','custom_field_(meta-key)',
];
const SMS_CODES = [
    'order_id','order_number','order_date','first_name','last_name','site_title',
    'status_from','status_to','billing_address','shipping_address',
    'custom_field_(meta-key)','product_titles',
];

// Free version defaults – advanced features disabled
const BLANK_FORM = {
    title         : '',
    slug          : '',
    post_status   : 'publish',
    icon_code     : '',
    color         : '#000000',
    text_color    : '#ffffff',
    reduce_stock  : '',
    enable_paid   : false,
    user_cancel   : false,
    email_enabled : false,
    email_address : '',
    email_bcc     : '',
    email_subject : '',
    email_heading : '',
    email_content : '',
    sms_enabled   : false,
    sms_content   : '',
};

// Strip wc- prefix for display in slug field
const stripWcPrefix = (slug) => slug?.startsWith('wc-') ? slug.slice(3) : (slug || '');

const UPGRADE_URL = 'https://www.tychesoftwares.com/products/custom-order-statuses-woocommerce-pro/?utm_source=coslite&utm_medium=notice&utm_campaign=upgrade';

function ProInlineNotice() {
    return (
        <div style={{
            display      : 'inline-flex',
            alignItems   : 'center',
            gap          : '6px',
            marginTop    : '6px',
            padding      : '8px 10px',
            background   : '#fef9ec',
            borderLeft   : '2px solid #f0c040',
            fontSize     : '12px',
            color        : '#1d2327',
            lineHeight   : 1.4,
        }}>
            <span>
                { __( 'This option is only available in the Pro version.', 'custom-order-statuses-woocommerce' ) }
                { ' ' }
                <a
                    href={ UPGRADE_URL }
                    target="_blank"
                    rel="noreferrer"
                    style={{ color: '#2271b1', fontWeight: 600, textDecoration: 'underline' }}
                >
                    { __( 'Upgrade to Pro', 'custom-order-statuses-woocommerce' ) }
                </a>
            </span>
        </div>
    );
}

// ── Color swatch ──────────────────────────────────────────────────────────────
function ColorSwatch({ color }) {
    return (
        <div style={{
            width: '32px', height: '24px',
            backgroundColor: color || 'transparent',
            border: '1px solid #c3c4c7',
            borderRadius: '3px',
            display: 'inline-block',
        }} />
    );
}

// ── Main screen ────────────────────────────────────────────────────────────────
function StatusManager({ noticeOperations, noticeUI }) {

    const { statuses, isLoading, refreshSection } = useSettings();

    const [showLoader, setShowLoader] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [search, setSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState([]);
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortField, setSortField] = useState('date');
    const [sortDir, setSortDir] = useState('desc');

    const { control, handleSubmit, reset, watch, setValue } = useForm({
        defaultValues: BLANK_FORM,
    });

    const watchTitle = watch('title');

    // Auto-generate slug from title (add only, don't change on edit)
    useEffect(() => {
        if (!editingId && watchTitle) {
            const slug = watchTitle.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 17);
            setValue('slug', slug);
        }
    }, [watchTitle, editingId, setValue]);

    const openAdd = () => {
        setEditingId(null);
        reset(BLANK_FORM);
        setIsModalOpen(true);
    };

    const openEdit = (status) => {
        setEditingId(status.id);
        reset({
            title: status.title,
            slug: stripWcPrefix(status.slug),
            post_status: status.post_status || 'publish',
            icon_code: status.icon_code || '',
            color: status.color || '#000000',
            text_color: status.text_color || '#ffffff',
            reduce_stock: '',  // free: always empty
            enable_paid: false,
            user_cancel: false,
            email_enabled: false,
            email_address: '',
            email_bcc: '',
            email_subject: '',
            email_heading: '',
            email_content: '',
            sms_enabled: false,
            sms_content: '',
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingId(null);
    };

    const onSubmit = async (data) => {
        if (!data.title?.trim()) {
            noticeOperations.createNotice({ 
                status: 'error', 
                content: __('Please enter a title.', 'custom-order-statuses-woocommerce') 
            });
            return;
        }
        if (!data.slug?.trim()) {
            noticeOperations.createNotice({ 
                status: 'error', 
                content: __('Please enter a slug.', 'custom-order-statuses-woocommerce') 
            });
            return;
        }
        
        setShowLoader(true);
        try {
            // Only core fields are saved; advanced fields forced to empty/false
            const payload = {
                title: data.title,
                slug: data.slug,
                post_status: data.post_status,
                icon_code: data.icon_code,
                color: data.color,
                text_color: data.text_color,
                reduce_stock: '',
                enable_paid: false,
                user_cancel: false,
                email_enabled: false,
                email_address: '',
                email_bcc: '',
                email_subject: '',
                email_heading: '',
                email_content: '',
                sms_enabled: false,
                sms_content: '',
            };
            await saveStatus(payload, editingId);
            closeModal();

            await refreshSection('statuses');
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: editingId
                    ? __('Status updated successfully.', 'custom-order-statuses-woocommerce')
                    : __('Status created successfully.', 'custom-order-statuses-woocommerce')
            });
        } catch (error) {
            console.error('Save status error:', error);
            noticeOperations.createNotice({
                status: 'error',
                content: __('Failed to save status.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
        }
    };

    const confirmDelete = async () => {
        setShowLoader(true);
        try {
            const ids = Array.isArray(deleteTarget) ? deleteTarget : [deleteTarget];
            await Promise.all(ids.map(id => deleteStatus(id)));
            setDeleteTarget(null);
            setSelectedIds([]);

            await refreshSection('statuses');
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __('Status deleted.', 'custom-order-statuses-woocommerce')
            });
        } catch (error) {
            console.error('Delete status error:', error);
            noticeOperations.createNotice({
                status: 'error',
                content: __('Failed to delete status.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
        }
    };

    // Sorting
    const toggleSort = (field) => {
        if (sortField === field) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
        else { setSortField(field); setSortDir('asc'); }
    };
    const sortArrow = (field) => sortField !== field ? ' ↕' : sortDir === 'asc' ? ' ↑' : ' ↓';

    const counts = {
        all: statuses.length,
        publish: statuses.filter(s => s.post_status === 'publish').length,
        draft: statuses.filter(s => s.post_status === 'draft').length,
    };

    const filtered = [...statuses]
        .filter(s => statusFilter === 'all' || s.post_status === statusFilter)
        .filter(s => !search || s.title.toLowerCase().includes(search.toLowerCase()) || s.slug.toLowerCase().includes(search.toLowerCase()))
        .sort((a, b) => {
            let va = sortField === 'title' ? a.title.toLowerCase() : a.date;
            let vb = sortField === 'title' ? b.title.toLowerCase() : b.date;
            return sortDir === 'asc' ? (va < vb ? -1 : va > vb ? 1 : 0) : (va > vb ? -1 : va < vb ? 1 : 0);
        });

    const allSelected = filtered.length > 0 && selectedIds.length === filtered.length;
    const toggleAll = () => setSelectedIds(allSelected ? [] : filtered.map(s => s.id));
    const toggleOne = (id) => setSelectedIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);

    if (isLoading) {
        return (
            <VStack style={{ marginTop: '20px' }} spacing={4}>
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            </VStack>
        );
    }

    return (
        <VStack style={{ marginTop: '20px' }} spacing={4}>
            {noticeUI}

            {/* ── Header ─────────────────────────────────────────────────── */}
            <HStack justify="space-between" alignment="center">
                <VStack spacing={1}>
                    <Heading level={3} style={{ margin: 0 }}>
                        {__('Custom Order Status', 'custom-order-statuses-woocommerce')}
                    </Heading>
                    <Text variant="muted">
                        {__('Create and manage custom order statuses for your WooCommerce store.', 'custom-order-statuses-woocommerce')}
                    </Text>
                </VStack>
                <Button variant="primary" onClick={openAdd} style={{ height: 'auto', padding: '8px 16px' }}>
                    + {__('Add New Order Status', 'custom-order-statuses-woocommerce')}
                </Button>
            </HStack>

            {/* ── Filter bar ─────────────────────────────────────────────── */}
            <div style={{ background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '12px 16px' }}>
                <div className="cos-filter-bar" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'nowrap', gap: '12px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0', flexShrink: 0 }}>
                        {[
                            { key: 'all', label: __('All', 'custom-order-statuses-woocommerce') },
                            { key: 'publish', label: __('Published', 'custom-order-statuses-woocommerce') },
                            { key: 'draft', label: __('Draft', 'custom-order-statuses-woocommerce') },
                        ].map((f, i) => (
                            <span key={f.key}>
                                {i > 0 && <span style={{ color: '#c3c4c7', margin: '0 6px' }}>|</span>}
                                <button
                                    type="button"
                                    onClick={() => setStatusFilter(f.key)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        cursor: 'pointer',
                                        padding: 0,
                                        fontSize: '13px',
                                        color: statusFilter === f.key ? '#1d2327' : '#0073aa',
                                        fontWeight: statusFilter === f.key ? 600 : 400
                                    }}
                                >
                                    {f.label} ({counts[f.key]})
                                </button>
                            </span>
                        ))}
                    </div>
                    <TextControl
                        value={search}
                        onChange={setSearch}
                        placeholder={__('Search Custom Order Status', 'custom-order-statuses-woocommerce')}
                        style={{ margin: 0, minWidth: '200px', flex: '0 0 220px' }}
                        __nextHasNoMarginBottom
                    />
                </div>
                {selectedIds.length > 0 && (
                    <HStack spacing={2} style={{ marginTop: '10px' }} alignment="left">
                        <Button variant="secondary" isDestructive onClick={() => setDeleteTarget([...selectedIds])}>
                            {__('Move to Trash', 'custom-order-statuses-woocommerce')}
                        </Button>
                        <Text variant="muted">{selectedIds.length} {__('item(s) selected', 'custom-order-statuses-woocommerce')}</Text>
                    </HStack>
                )}
            </div>

            {/* ── Table ──────────────────────────────────────────────────── */}
            <div className="cos-table-scroll" style={{ background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f9f9f9', borderBottom: '1px solid #c3c4c7' }}>
                            <th style={TH.cb}><input type="checkbox" checked={allSelected} onChange={toggleAll} /></th>
                            <th style={{ ...TH.title, cursor: 'pointer' }} onClick={() => toggleSort('title')}>
                                {__('Status name', 'custom-order-statuses-woocommerce')}{sortArrow('title')}
                            </th>
                            <th style={TH.icon}>{__('Icon', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.color}>{__('Status Color', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.color}>{__('Text Color', 'custom-order-statuses-woocommerce')}</th>
                            <th style={{ ...TH.date, cursor: 'pointer' }} onClick={() => toggleSort('date')}>
                                {__('Date', 'custom-order-statuses-woocommerce')}{sortArrow('date')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr><td colSpan={6} style={{ padding: '32px', textAlign: 'center', color: '#646970' }}>
                                {__('No custom order statuses found. Click "Add New Order Status" to create one.', 'custom-order-statuses-woocommerce')}
                            </td></tr>
                        ) : filtered.map((status, i) => (
                            <tr key={status.id} style={{ borderBottom: i < filtered.length - 1 ? '1px solid #f0f0f0' : 'none', background: selectedIds.includes(status.id) ? '#f0f6fc' : 'transparent' }}>
                                <td style={{ ...TD, ...TH.cb }}><input type="checkbox" checked={selectedIds.includes(status.id)} onChange={() => toggleOne(status.id)} /></td>
                                <td style={TD}>
                                    <VStack spacing={1}>
                                        <button type="button" onClick={() => openEdit(status)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#0073aa', fontWeight: 600, fontSize: '13px', padding: 0, textAlign: 'left' }}>
                                            {status.title}
                                        </button>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '12px', color: '#646970' }}>
                                            <Text style={{ color: '#999' }}>{__('ID: ', 'custom-order-statuses-woocommerce')}{status.id}</Text>
                                            <span>|</span>
                                            <button type="button" onClick={() => openEdit(status)} style={LINK_BTN}>{__('Edit', 'custom-order-statuses-woocommerce')}</button>
                                            <span>|</span>
                                            <button type="button" onClick={() => setDeleteTarget(status.id)} style={{ ...LINK_BTN, color: '#b32d2e' }}>{__('Trash', 'custom-order-statuses-woocommerce')}</button>
                                        </div>
                                    </VStack>
                                </td>
                                <td style={TD}>{status.icon_code ? <IconTableCell unicode={status.icon_code} /> : <Text style={{ color: '#646970' }}>{__('No Icon', 'custom-order-statuses-woocommerce')}</Text>}</td>
                                <td style={TD}><ColorSwatch color={status.color} /></td>
                                <td style={TD}><ColorSwatch color={status.text_color} /></td>
                                <td style={{ ...TD, color: '#646970' }}>
                                    <VStack spacing={0}>
                                        <span>{status.post_status === 'publish' ? __('Published', 'custom-order-statuses-woocommerce') : __('Draft', 'custom-order-statuses-woocommerce')}</span>
                                        <span style={{ fontSize: '12px' }}>{status.date}</span>
                                    </VStack>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Text variant="muted">{filtered.length} {__('item(s)', 'custom-order-statuses-woocommerce')}</Text>

            <ConfirmDialog
                isOpen={!!deleteTarget}
                cancelButtonText={__('Cancel', 'custom-order-statuses-woocommerce')}
                confirmButtonText={__('Move to Trash', 'custom-order-statuses-woocommerce')}
                onCancel={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
            >
                {Array.isArray(deleteTarget)
                    ? `${__('Are you sure you want to delete', 'custom-order-statuses-woocommerce')} ${deleteTarget.length} ${__('item(s)?', 'custom-order-statuses-woocommerce')}`
                    : __('Are you sure you want to delete this custom order status?', 'custom-order-statuses-woocommerce')}
            </ConfirmDialog>

            {/* ── Add / Edit Modal ─────────────────────────────────────────── */}
            {isModalOpen && (
                <Modal
                    title={editingId ? __('Edit Order Status', 'custom-order-statuses-woocommerce') : __('Add New Order Status', 'custom-order-statuses-woocommerce')}
                    onRequestClose={closeModal}
                    style={{ maxWidth: '780px', width: '90vw', maxHeight: '90vh', height: 'auto' }}
                    className="cos-status-modal"
                >
                    <Text variant="muted" style={{ marginBottom: '20px', display: 'block' }}>
                        {editingId
                            ? __('Update the order status details.', 'custom-order-statuses-woocommerce')
                            : __('Give this status a name, icon, and color.', 'custom-order-statuses-woocommerce')}
                    </Text>

                    <form onSubmit={handleSubmit(onSubmit)}>
                        <VStack className="cos_setting_section" spacing={6}>
                            {/* Core fields: Title, Active */}
                            <div style={{ background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '20px', marginBottom: '20px' }}>
                                <TextControl
                                    label={__('Status name', 'custom-order-statuses-woocommerce')}
                                    value={watch('title') || ''}
                                    onChange={(val) => setValue('title', val)}
                                    placeholder={__('e.g., Ready to Ship', 'custom-order-statuses-woocommerce')}
                                    __nextHasNoMarginBottom
                                />
                                <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #f0f0f0' }}>
                                    <ToggleControl
                                        label={__('Active', 'custom-order-statuses-woocommerce')}
                                        help={watch('post_status') === 'publish' 
                                            ? __('Status is active and available for use.', 'custom-order-statuses-woocommerce')
                                            : __('Status is inactive (draft) and not available for use.', 'custom-order-statuses-woocommerce')}
                                        checked={watch('post_status') === 'publish'}
                                        onChange={(checked) => setValue('post_status', checked ? 'publish' : 'draft')}
                                        __nextHasNoMarginBottom
                                    />
                                </div>
                            </div>

                            {/* Status Details (free) */}
                            <SettingsCard
                                heading={__('Status Details', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'slug',
                                        label: __('Slug * (URL identifier)', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <VStack spacing={1}>
                                                <TextControl value={f.value ?? ''} onChange={f.onChange} placeholder="e.g., new-arrivals" disabled={!!editingId} __nextHasNoMarginBottom />
                                                <Text variant="muted" size="small">
                                                    {__('No ', 'custom-order-statuses-woocommerce')}<code>wc-</code> {__('prefix needed. Max 17 characters.', 'custom-order-statuses-woocommerce')}
                                                </Text>
                                            </VStack>
                                        ),
                                    },
                                    { name: 'icon_code', label: __('Icon', 'custom-order-statuses-woocommerce'), render: (f) => <IconPicker value={f.value ?? ''} onChange={f.onChange} /> },
                                    { name: 'color', label: __('Status Color', 'custom-order-statuses-woocommerce'), render: (f) => <input type="color" value={f.value ?? '#000000'} onChange={(e) => f.onChange(e.target.value)} style={{ width: '80px', height: '36px', padding: '2px', border: '1px solid #8c8f94', borderRadius: '4px', cursor: 'pointer' }} /> },
                                    { name: 'text_color', label: __('Text Color', 'custom-order-statuses-woocommerce'), render: (f) => <input type="color" value={f.value ?? '#ffffff'} onChange={(e) => f.onChange(e.target.value)} style={{ width: '80px', height: '36px', padding: '2px', border: '1px solid #8c8f94', borderRadius: '4px', cursor: 'pointer' }} /> },
                                ]}
                            />

                            {/* Stock & Inventory – Pro only (disabled) */}
                            <SettingsCard
                                heading={__('Stock & Inventory', 'custom-order-statuses-woocommerce')}
                                headingExtra={<ProNotice feature={__('Stock management', 'custom-order-statuses-woocommerce')} />}
                                control={control}
                                fields={[{
                                    name: 'reduce_stock',
                                    label: __('Stock update', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <SelectControl
                                            value=""
                                            onChange={() => {}}
                                            options={[
                                                { label: __('No stock change', 'custom-order-statuses-woocommerce'), value: '' },
                                                { label: __('Increase Stock Level', 'custom-order-statuses-woocommerce'), value: 'increase' },
                                                { label: __('Decrease Stock Level', 'custom-order-statuses-woocommerce'), value: 'decrease' },
                                            ]}
                                            disabled
                                            help={__('When the order status changes to this state it will increase/decrease the stock as per the setting below.', 'custom-order-statuses-woocommerce')}
                                            __nextHasNoMarginBottom
                                        />
                                    ),
                                }]}
                            />

                            {/* Order Behaviour – "Mark as paid" disabled, "Allow customer cancellation" disabled + inline notice */}
                            <SettingsCard
                                heading={__('Order Behaviour', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'enable_paid',
                                        label: __('Mark as paid', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <ToggleControl
                                                checked={!!f.value}
                                                onChange={f.onChange}
                                                help={__('Default paid statuses: Processing, Completed.', 'custom-order-statuses-woocommerce')}
                                                __nextHasNoMarginBottom
                                            />
                                        ),
                                    },
                                    {
                                        name: 'user_cancel',
                                        label: __('Allow customer cancellation', 'custom-order-statuses-woocommerce'),
                                        render: () => (
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                                <ToggleControl
                                                    checked={false}
                                                    disabled
                                                    help={__('Choose whether the customer can cancel orders when this status is applied or not.', 'custom-order-statuses-woocommerce')}
                                                    __nextHasNoMarginBottom
                                                />
                                                <ProInlineNotice />
                                            </div>
                                        ),
                                    },
                                ]}
                            />

                            {/* Email Settings – Pro only (disabled) */}
                            <SettingsCard
                                heading={__('Email Settings', 'custom-order-statuses-woocommerce')}
                                headingExtra={<ProNotice feature={__('Email Notifications', 'custom-order-statuses-woocommerce')} />}
                                control={control}
                                fields={[
                                    { name: 'email_enabled', label: __('Send email notification', 'custom-order-statuses-woocommerce'), render: () => <ToggleControl checked={false} disabled __nextHasNoMarginBottom /> },
                                    { name: 'email_address', label: __('Email address', 'custom-order-statuses-woocommerce'), render: () => <TextControl value="" disabled placeholder="customer@example.com" __nextHasNoMarginBottom /> },
                                    { name: 'email_bcc', label: __('BCC', 'custom-order-statuses-woocommerce'), render: () => <TextControl value="" disabled placeholder="admin@example.com" __nextHasNoMarginBottom /> },
                                    { name: 'email_subject', label: __('Email subject', 'custom-order-statuses-woocommerce'), render: () => <TextControl value="" disabled help={<ShortcodeHelp codes={EMAIL_SUBJECT_CODES} />} __nextHasNoMarginBottom /> },
                                    { name: 'email_heading', label: __('Email heading', 'custom-order-statuses-woocommerce'), render: () => <TextControl value="" disabled help={<ShortcodeHelp codes={EMAIL_SUBJECT_CODES} />} __nextHasNoMarginBottom /> },
                                    { name: 'email_content', label: __('Email content', 'custom-order-statuses-woocommerce'), render: () => <TextareaControl value="" disabled rows={6} help={<ShortcodeHelp codes={EMAIL_CONTENT_CODES} />} __nextHasNoMarginBottom /> },
                                ]}
                            />

                            {/* SMS Settings – Pro only (disabled) */}
                            <SettingsCard
                                heading={__('SMS Settings', 'custom-order-statuses-woocommerce')}
                                headingExtra={<ProNotice feature={__('SMS Notifications', 'custom-order-statuses-woocommerce')} />}
                                control={control}
                                fields={[
                                    { name: 'sms_enabled', label: __('Send SMS notification', 'custom-order-statuses-woocommerce'), render: () => <ToggleControl checked={false} disabled __nextHasNoMarginBottom /> },
                                    {
                                        name: 'sms_content',
                                        label: __('SMS content', 'custom-order-statuses-woocommerce'),
                                        render: () => (
                                            <VStack spacing={1}>
                                                <TextareaControl value="" disabled rows={6} __nextHasNoMarginBottom />
                                                <Text variant="muted" size="small">
                                                    <em>
                                                        {__('Replaced values:', 'custom-order-statuses-woocommerce')}{' '}
                                                        {SMS_CODES.map((c, i) => (
                                                            <span key={c}>
                                                                <code>{`{${c}}`}</code>{i < SMS_CODES.length - 1 ? ' , ' : ''}
                                                            </span>
                                                        ))}
                                                        {'. '}{__('You can also use shortcodes here.', 'custom-order-statuses-woocommerce')}
                                                    </em>
                                                </Text>
                                                <Text variant="muted" size="small">
                                                    {__('Note : For', 'custom-order-statuses-woocommerce')}{' '}
                                                    <code>{'{custom_field_(meta-key)}'}</code>
                                                    {__(' shortcode add your correct meta key in place of (meta-key).', 'custom-order-statuses-woocommerce')}
                                                </Text>
                                            </VStack>
                                        ),
                                    },
                                ]}
                            />

                            {/* Footer actions */}
                            <HStack justify={editingId ? 'space-between' : 'flex-end'} style={{ borderTop: '1px solid #c3c4c7', paddingTop: '16px', marginTop: '8px' }}>
                                {editingId && (
                                    <Button variant="secondary" isDestructive onClick={() => { setDeleteTarget(editingId); closeModal(); }}>
                                        {__('Delete', 'custom-order-statuses-woocommerce')}
                                    </Button>
                                )}
                                <HStack spacing={2} expanded={false}>
                                    <Button variant="primary" type="submit">
                                        {editingId ? __('Update', 'custom-order-statuses-woocommerce') : __('Save', 'custom-order-statuses-woocommerce')}
                                    </Button>
                                    <Button variant="secondary" onClick={closeModal}>{__('Cancel', 'custom-order-statuses-woocommerce')}</Button>
                                </HStack>
                            </HStack>
                        </VStack>
                    </form>
                </Modal>
            )}

            {showLoader && <div className="cos_loader"><Spinner style={{ width: '30px', height: '30px' }} /></div>}
        </VStack>
    );
}

// ── Style constants ────────────────────────────────────────────────────────────
const TH = {
    cb: { width: '40px', padding: '10px 12px', textAlign: 'left' },
    title: { padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327', userSelect: 'none' },
    icon: { width: '140px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
    color: { width: '100px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
    date: { width: '160px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327', userSelect: 'none', cursor: 'pointer' },
};
const TD = { padding: '12px', verticalAlign: 'middle' };
const LINK_BTN = { background: 'none', border: 'none', cursor: 'pointer', color: '#646970', padding: 0, font: 'inherit', fontSize: '12px' };

export default withNotices(StatusManager);