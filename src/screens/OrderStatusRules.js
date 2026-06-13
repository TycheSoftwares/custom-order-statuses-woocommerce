/**
 * src/screens/OrderStatusRules.js
 * Free version – original Pro styling, all fields disabled, no AsyncSelect.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button, Modal, SelectControl, ToggleControl, Spinner, withNotices, Notice,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalInputControl as InputControl,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import Select from 'react-select';
import { useForm } from 'react-hook-form';
import MultiDatePicker from '../components/MultiDatePicker';
import ProNotice from '../components/ProNotice';
import HelpTip from '../components/HelpTip';
import SettingsCard from '../components/SettingsCard';
import getOptions from '../data/api/getOptions';

// Constants
const DAY_OPTIONS = [
    { value: 1, label: __('Monday', 'custom-order-statuses-woocommerce') },
    { value: 2, label: __('Tuesday', 'custom-order-statuses-woocommerce') },
    { value: 3, label: __('Wednesday', 'custom-order-statuses-woocommerce') },
    { value: 4, label: __('Thursday', 'custom-order-statuses-woocommerce') },
    { value: 5, label: __('Friday', 'custom-order-statuses-woocommerce') },
    { value: 6, label: __('Saturday', 'custom-order-statuses-woocommerce') },
    { value: 7, label: __('Sunday', 'custom-order-statuses-woocommerce') },
];
const TIME_UNIT_OPTIONS = [
    { label: __('Minutes', 'custom-order-statuses-woocommerce'), value: 'minutes' },
    { label: __('Hours', 'custom-order-statuses-woocommerce'), value: 'hours' },
    { label: __('Days', 'custom-order-statuses-woocommerce'), value: 'days' },
    { label: __('Weeks', 'custom-order-statuses-woocommerce'), value: 'weeks' },
];

// Dummy rule for free version
const DUMMY_RULE = {
    id: 1,
    name: __('Example: Auto‑complete after 3 days (Pro feature)', 'custom-order-statuses-woocommerce'),
    status_from: 'processing',
    status_to: 'completed',
    time_trigger: 3,
    time_unit: 'days',
    skip_days: [],
    skip_dates: '',
    payment_methods: [],
    shipping_methods: [],
    products: [],
    categories: [],
    min_amount: 50,
    min_qty: 2,
    user_roles: [],
    countries: [],
    enabled: true,
};

// Helper: safely get selected values for react‑select
const getSelectedValues = (selectedArray, allOptions) => {
    if (!Array.isArray(selectedArray) || !Array.isArray(allOptions)) return [];
    return allOptions.filter(opt => selectedArray.includes(opt.value));
};

// No‑op load function for the disabled product selector (replaces AsyncSelect)
const noopLoadOptions = () => Promise.resolve([]);

function OrderStatusRules({ noticeOperations, noticeUI }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [options, setOptions] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    // Dummy form — only needed so SettingsCard's Controller has a valid control.
    // All render functions read from DUMMY_RULE directly; nothing is submitted.
    const { control } = useForm({
        defaultValues: {
            name:             DUMMY_RULE.name,
            enabled:          DUMMY_RULE.enabled,
            status_from:      DUMMY_RULE.status_from,
            status_to:        DUMMY_RULE.status_to,
            time_trigger:     DUMMY_RULE.time_trigger,
            time_unit:        DUMMY_RULE.time_unit,
            skip_days:        DUMMY_RULE.skip_days,
            skip_dates:       DUMMY_RULE.skip_dates,
            payment_methods:  DUMMY_RULE.payment_methods,
            shipping_methods: DUMMY_RULE.shipping_methods,
            products:         DUMMY_RULE.products,
            categories:       DUMMY_RULE.categories,
            min_amount:       DUMMY_RULE.min_amount,
            min_qty:          DUMMY_RULE.min_qty,
            user_roles:       DUMMY_RULE.user_roles,
            countries:        DUMMY_RULE.countries,
        },
    });

    // Load options only once
    useEffect(() => {
        const fetchData = async () => {
            setIsLoading(true);
            try {
                const data = await getOptions();
                setOptions({
                    order_statuses: data?.order_statuses ?? [],
                    payment_methods: data?.payment_methods ?? [],
                    shipping_methods: data?.shipping_methods ?? [],
                    product_categories: data?.product_categories ?? [],
                    user_roles: data?.user_roles ?? [],
                    countries: data?.countries ?? [],
                });
            } catch (error) {
                console.error('Fetch error:', error);
                setOptions({
                    order_statuses: [],
                    payment_methods: [],
                    shipping_methods: [],
                    product_categories: [],
                    user_roles: [],
                    countries: [],
                });
            } finally {
                setIsLoading(false);
            }
        };
        fetchData();
    }, []);

    const openModal = () => setIsModalOpen(true);
    const closeModal = () => setIsModalOpen(false);

    // Wait until options are loaded
    if (isLoading || !options) {
        return (
            <VStack style={{ marginTop: '20px' }} spacing={4}>
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            </VStack>
        );
    }

    const {
        order_statuses = [],
        payment_methods = [],
        shipping_methods = [],
        product_categories = [],
        user_roles = [],
        countries = [],
    } = options;

    const statusLabel = (slug) => {
        const found = order_statuses.find(s => s.value === slug);
        return found ? found.label : slug;
    };

    return (
        <VStack style={{ marginTop: '20px' }} spacing={4}>
            {noticeUI}

            <ProNotice feature={__('Custom Order Status Rules', '...')} />

            {/* Header with Add button disabled */}
            <HStack justify="space-between" alignment="center">
                <VStack spacing={1}>
                    <Heading level={3} style={{ margin: 0 }}>
                        {__('Order Status Rules', 'custom-order-statuses-woocommerce')}
                    </Heading>
                    <Text variant="muted">
                        {__('Automatically move orders between statuses based on time, payment, shipping, and more.', 'custom-order-statuses-woocommerce')}
                    </Text>
                </VStack>
                <Button variant="primary" disabled style={{ height: 'auto', padding: '8px 16px', opacity: 0.6 }}>
                    + {__('Add New Rule', 'custom-order-statuses-woocommerce')}
                </Button>
            </HStack>

            {/* Table with dummy rule */}
            <div className="cos-table-scroll" style={{ background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                    <thead>
                        <tr style={{ background: '#f9f9f9', borderBottom: '1px solid #c3c4c7' }}>
                            <th style={TH.status}>{__('Status', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.name}>{__('Rule Name', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.from}>{__('From', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.to}>{__('To', 'custom-order-statuses-woocommerce')}</th>
                            <th style={TH.time}>{__('Time Trigger', 'custom-order-statuses-woocommerce')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style={{ ...TD, ...TH.status }}>
                                <ToggleControl
                                    checked={DUMMY_RULE.enabled}
                                    onChange={() => {}}
                                    disabled
                                    __nextHasNoMarginBottom
                                />
                            </td>
                            <td style={TD}>
                                <VStack spacing={1}>
                                    <button
                                        type="button"
                                        onClick={openModal}
                                        style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#0073aa', fontWeight: 600, fontSize: '13px', padding: 0, textAlign: 'left' }}
                                    >
                                        {DUMMY_RULE.name}
                                    </button>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '2px', fontSize: '12px', color: '#646970', marginTop: '2px' }}>
                                        <button type="button" onClick={openModal} style={LINK_BTN}>
                                            {__('Edit', 'custom-order-statuses-woocommerce')}
                                        </button>
                                        <span style={{ color: '#c3c4c7', padding: '0 1px' }}>|</span>
                                        <button type="button" style={{ ...LINK_BTN, color: '#b32d2e', opacity: 0.6, cursor: 'not-allowed' }} disabled>
                                            {__('Delete', 'custom-order-statuses-woocommerce')}
                                        </button>
                                    </div>
                                </VStack>
                            </td>
                            <td style={TD}>{statusLabel(DUMMY_RULE.status_from)}</td>
                            <td style={TD}>{statusLabel(DUMMY_RULE.status_to)}</td>
                            <td style={{ ...TD, color: '#646970' }}>
                                {DUMMY_RULE.time_trigger} {DUMMY_RULE.time_unit}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Text variant="muted">1 {__('rule(s)', 'custom-order-statuses-woocommerce')}</Text>

            {/* Modal with original Pro styling, all fields disabled */}
            {isModalOpen && (
                <Modal
                    title={__('Edit Rule (Pro Feature)', 'custom-order-statuses-woocommerce')}
                    onRequestClose={closeModal}
                    style={{ maxWidth: '780px', width: '90vw', maxHeight: '90vh', height: 'auto' }}
                >
                    <form>
                        <VStack className="cos_setting_section" spacing={6}>
                            <SettingsCard
                                heading={__('Rule Details', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'name',
                                        label: __('Rule Name', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <InputControl value={DUMMY_RULE.name} disabled />,
                                    },
                                    {
                                        name: 'enabled',
                                        label: __('Rule active', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <ToggleControl checked={DUMMY_RULE.enabled} disabled __nextHasNoMarginBottom />,
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Status Transition', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'status_from',
                                        label: __('From status', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <SelectControl value={DUMMY_RULE.status_from} options={order_statuses} disabled />,
                                    },
                                    {
                                        name: 'status_to',
                                        label: __('To status', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <SelectControl value={DUMMY_RULE.status_to} options={order_statuses} disabled />,
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Timing', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'time_trigger',
                                        label: __('Delay', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', alignItems: 'center', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Set it to zero for an immediate status update.', 'custom-order-statuses-woocommerce')} />
                                                <NumberControl value={DUMMY_RULE.time_trigger} disabled />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'time_unit',
                                        label: __('Unit', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <SelectControl value={DUMMY_RULE.time_unit} options={TIME_UNIT_OPTIONS} disabled />,
                                    },
                                    {
                                        name: 'skip_days',
                                        label: __('Exclude days of week', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Ignored if empty, or if all seven days are selected.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={DAY_OPTIONS} value={getSelectedValues(DUMMY_RULE.skip_days, DAY_OPTIONS)} isDisabled />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'skip_dates',
                                        label: __('Exclude specific dates', 'custom-order-statuses-woocommerce'),
                                        render: (f) => <MultiDatePicker value={DUMMY_RULE.skip_dates ?? ''} disabled />,
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Payment & Shipping', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'payment_methods',
                                        label: __('Payment methods', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Required payment gateways.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={payment_methods} value={getSelectedValues(DUMMY_RULE.payment_methods, payment_methods)} isDisabled />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'shipping_methods',
                                        label: __('Shipping methods', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Required shipping methods.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={shipping_methods} value={getSelectedValues(DUMMY_RULE.shipping_methods, shipping_methods)} isDisabled />
                                            </div>
                                        ),
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Products & Categories', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'products',
                                        label: __('Products', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Apply only for orders with specific products.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={[]} value={[]} isDisabled placeholder={__('Pro feature – upgrade', 'custom-order-statuses-woocommerce')} />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'categories',
                                        label: __('Categories', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Apply only for orders with selected categories.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={product_categories} value={getSelectedValues(DUMMY_RULE.categories, product_categories)} isDisabled />
                                            </div>
                                        ),
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Order Amount & Quantity', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'min_amount',
                                        label: __('Min. order amount', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', alignItems: 'center', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Minimum order amount.', 'custom-order-statuses-woocommerce')} />
                                                <NumberControl value={DUMMY_RULE.min_amount} disabled />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'min_qty',
                                        label: __('Min. order quantity', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', alignItems: 'center', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Minimum order quantity.', 'custom-order-statuses-woocommerce')} />
                                                <NumberControl value={DUMMY_RULE.min_qty} disabled />
                                            </div>
                                        ),
                                    },
                                ]}
                            />

                            <SettingsCard
                                heading={__('Filters', 'custom-order-statuses-woocommerce')}
                                control={control}
                                fields={[
                                    {
                                        name: 'user_roles',
                                        label: __('User roles', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Apply only for specific user roles.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={user_roles} value={getSelectedValues(DUMMY_RULE.user_roles, user_roles)} isDisabled />
                                            </div>
                                        ),
                                    },
                                    {
                                        name: 'countries',
                                        label: __('Countries', 'custom-order-statuses-woocommerce'),
                                        render: (f) => (
                                            <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                                <HelpTip message={__('Apply only for specific billing countries.', 'custom-order-statuses-woocommerce')} />
                                                <Select isMulti options={countries} value={getSelectedValues(DUMMY_RULE.countries, countries)} isDisabled />
                                            </div>
                                        ),
                                    },
                                ]}
                            />

                            <HStack justify="space-between" style={{ borderTop: '1px solid #c3c4c7', paddingTop: '16px' }}>
                                <Button variant="secondary" isDestructive type="button" disabled style={{ opacity: 0.6 }}>
                                    {__('Delete', 'custom-order-statuses-woocommerce')}
                                </Button>
                                <HStack spacing={2} expanded={false}>
                                    <Button variant="primary" disabled style={{ opacity: 0.6 }}>
                                        {__('Update Rule', 'custom-order-statuses-woocommerce')}
                                    </Button>
                                    <Button variant="secondary" onClick={closeModal}>
                                        {__('Close', 'custom-order-statuses-woocommerce')}
                                    </Button>
                                </HStack>
                            </HStack>
                        </VStack>
                    </form>
                </Modal>
            )}
        </VStack>
    );
}

const TH = {
    status: { width: '80px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
    name: { padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327', userSelect: 'none' },
    from: { width: '160px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
    to: { width: '160px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
    time: { width: '140px', padding: '10px 12px', textAlign: 'left', fontWeight: 600, color: '#1d2327' },
};
const TD = { padding: '12px', verticalAlign: 'middle' };
const LINK_BTN = { background: 'none', border: 'none', cursor: 'pointer', color: '#646970', padding: 0, font: 'inherit', fontSize: '12px' };

export default withNotices(OrderStatusRules);