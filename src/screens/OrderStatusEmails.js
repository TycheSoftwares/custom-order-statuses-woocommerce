/**
 * src/screens/OrderStatusEmails.js
 * Free version – Customer Email section fully working,
 * Admin Alert section disabled with Pro notice.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button, ToggleControl, Spinner, withNotices,
    SelectControl, TextareaControl,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalInputControl as InputControl,
    __experimentalNumberControl as NumberControl,
    __experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import Select from 'react-select';
import { useForm } from 'react-hook-form';
import SettingsCard from '../components/SettingsCard';
import ShortcodeHelp from '../components/ShortcodeHelp';
import HelpTip from '../components/HelpTip';
import ProNotice, { ProInlineNotice } from '../components/ProNotice';
import { updateSettings, resetSection } from '../data/api';
import { useSettings } from '../context/SettingsContext';

const SUBJECT_CODES = ['order_id','order_number','order_date','site_title','status_from','status_to'];
const CONTENT_CODES = ['order_id','order_number','order_date','order_details','first_name','last_name',
    'billing_address','shipping_address','product_titles','site_title','status_from','status_to','custom_field_(meta-key)'];
const ADMIN_CODES   = ['order_id','order_number','order_date','order_details','first_name','last_name',
    'billing_address','shipping_address','product_titles','site_title','order_status'];

// Default values for Customer Email (empty but functional)
const DEFAULT_EMAIL_VALUES = {
    enabled: false,
    statuses: [],
    address: '',
    bcc: '',
    subject: '',
    heading: '',
    content: '',
};

// Default values for Admin Alert (disabled – static dummy)
const ADMIN_DISABLED_VALUES = {
    enabled: false,
    statuses: [],
    interval_time: 1,
    interval: 'days',
    address: '',
    subject: '',
    heading: '',
    content: '',
};

const StatusSelect = ({ field, options }) => {
    const value = field.value ?? [];
    const selected = options.filter(opt => value.includes(opt.value));

    return (
        <Select
            isMulti
            options={options}
            value={selected}
            onChange={(sel) => field.onChange((sel ?? []).map(o => o.value))}
            placeholder={__('Leave blank for all custom statuses…', 'custom-order-statuses-woocommerce')}
            classNamePrefix="cos-select"
        />
    );
};

function OrderStatusEmails({ noticeOperations, noticeUI }) {
    const [showLoader, setShowLoader] = useState(false);
    const [statusOptions, setStatusOptions] = useState([]);
    const [customStatusOptions, setCustomStatusOptions] = useState([]);
    const [isResetOpen, setIsResetOpen] = useState(false);

    const {
        settings,
        options,
        isLoading: globalLoading,
        loadedSections,
        fetchSection,
        updateSettingsData
    } = useSettings();

    const [isFormReady, setIsFormReady] = useState(() => {
        return loadedSections.settings && loadedSections.options;
    });

    const currentEmailValues = settings?.emails || {};
    const currentAdminValues = settings?.admin_email || {};

    const emailForm = useForm({
        defaultValues: {
            ...DEFAULT_EMAIL_VALUES,
            ...currentEmailValues,
        },
    });

    // Admin form – never saves, just a placeholder
    const adminForm = useForm({
        defaultValues: ADMIN_DISABLED_VALUES,
    });

    // Load options for select dropdowns
    useEffect(() => {
        if (options && loadedSections.options) {
            const statuses = (options?.order_statuses ?? []).map(status => ({
                ...status,
                value: status.value.startsWith('wc-')
                    ? status.value
                    : `wc-${status.value}`
            }));

            const custom_statuses = (options?.custom_order_statuses ?? []).map(status => ({
                ...status,
                value: status.value.startsWith('wc-')
                    ? status.value
                    : `wc-${status.value}`
            }));

            setStatusOptions(statuses);
            setCustomStatusOptions(custom_statuses);
        }
    }, [options, loadedSections.options]);

    // Load saved email settings
    useEffect(() => {
        if (settings?.emails && loadedSections.settings) {
            emailForm.reset(settings.emails);
        }
        if (loadedSections.settings && loadedSections.options && !isFormReady) {
            setIsFormReady(true);
        }
    }, [settings, loadedSections.settings, loadedSections.options, emailForm.reset, isFormReady]);

    // Fetch missing data
    useEffect(() => {
        if (!loadedSections.settings) fetchSection('settings');
        if (!loadedSections.options) fetchSection('options');
    }, []);

    const handleSave = async () => {
        const isValid = await emailForm.trigger();
        if (!isValid) return;

        const emailData = emailForm.getValues();

        setShowLoader(true);
        try {
            const current = settings || {};
            await updateSettings({ ...current, emails: emailData });
            updateSettingsData('settings', { ...current, emails: emailData });

            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __('Settings saved successfully.', 'custom-order-statuses-woocommerce')
            });
        } catch {
            noticeOperations.createNotice({
                status: 'error',
                content: __('Error saving settings.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
        }
    };

    const handleReset = async () => {
        setShowLoader(true);
        try {
            const emailDefaults = await resetSection('emails');
            if (emailDefaults) emailForm.reset(emailDefaults);

            const current = settings || {};
            updateSettingsData('settings', { ...current, emails: emailDefaults });

            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __('Settings reset to defaults.', 'custom-order-statuses-woocommerce')
            });
        } catch {
            noticeOperations.createNotice({
                status: 'error',
                content: __('Reset failed.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
            setIsResetOpen(false);
        }
    };

    const isLoading = globalLoading || !loadedSections.settings || !loadedSections.options || !isFormReady;

    return (
        <VStack style={{ marginTop: '30px' }}>
            {noticeUI}

            {isLoading ? (
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            ) : (
                <VStack className="cos_setting_section" spacing={10}>

                    {/* ── Section 1: Customer Email Notifications (Fully functional) ── */}
                    <SettingsCard
                        heading={__('Customer Email Notifications', 'custom-order-statuses-woocommerce')}
                        subHeading={__('Notify customers on status change. Note: overridden by status-level email settings.', 'custom-order-statuses-woocommerce')}
                        control={emailForm.control}
                        fields={[
                            {
                                name: 'enabled', defaultValue: false,
                                label: __('Enable customer notifications', 'custom-order-statuses-woocommerce'),
                                render: (f) => <ToggleControl checked={!!f.value} onChange={f.onChange} __nextHasNoMarginBottom />
                            },
                            {
                                name: 'statuses', defaultValue: [],
                                label: __('Statuses', 'custom-order-statuses-woocommerce'),
                                render: (f) => (
                                    <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                        <HelpTip message={__('Custom statuses to send emails. Leave blank to send emails on all custom statuses.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                        <StatusSelect field={f} options={customStatusOptions} />
                                    </div>
                                ),
                            },
                            {
                                name: 'address', defaultValue: '',
                                label: __('Email address', 'custom-order-statuses-woocommerce'),
                                render: (f) => (
                                    <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                        <HelpTip message={__('Comma separated list of emails. Leave blank to send emails to admin.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                        <InputControl value={f.value ?? ''} onChange={f.onChange} help={__('Use {customer_email} or {admin_email}. Leave blank for admin.', 'custom-order-statuses-woocommerce')} />
                                    </div>
                                ),
                            },
                            {
                                name: 'bcc', defaultValue: '',
                                label: __('BCC', 'custom-order-statuses-woocommerce'),
                                render: (f) => (
                                    <div style={{ display: 'flex', alignItems: 'center', marginLeft: '-32px' }}>
                                        <HelpTip message={__('Comma separated list of emails.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                        <InputControl style={{ flex: 1 }} value={f.value ?? ''} onChange={f.onChange} disabled />
                                        <div style={{ marginLeft: '8px', display: 'flex', alignItems: 'center' }}>
                                            <ProInlineNotice />
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                name: 'subject', defaultValue: '',
                                label: __('Email subject', 'custom-order-statuses-woocommerce'),
                                render: (f) => <InputControl value={f.value ?? ''} onChange={f.onChange} help={<ShortcodeHelp codes={SUBJECT_CODES} />} />
                            },
                            {
                                name: 'heading', defaultValue: '',
                                label: __('Email heading', 'custom-order-statuses-woocommerce'),
                                render: (f) => <InputControl value={f.value ?? ''} onChange={f.onChange} help={<ShortcodeHelp codes={SUBJECT_CODES} />} />
                            },
                            {
                                name: 'content', defaultValue: '',
                                label: __('Email content', 'custom-order-statuses-woocommerce'),
                                render: (f) => <TextareaControl value={f.value ?? ''} onChange={f.onChange} rows={8} help={<ShortcodeHelp codes={CONTENT_CODES} />} __nextHasNoMarginBottom />
                            },
                        ]}
                    />

                    <hr style={{ border: 'none', borderTop: '2px solid #e4e7ec', margin: '4px 0' }} />

                    {/* ── Section 2: Admin Alert Emails (Pro feature – disabled) ── */}
                    <div>
                        <ProNotice feature={__('Admin Alert Emails', 'custom-order-statuses-woocommerce')} />
                        <SettingsCard
                            heading={__('Admin Alert Emails', 'custom-order-statuses-woocommerce')}
                            subHeading={__('Alert the admin when an order stays in the same status too long.', 'custom-order-statuses-woocommerce')}
                            control={adminForm.control}
                            fields={[
                                {
                                    name: 'enabled', defaultValue: false,
                                    label: __('Enable admin alerts', 'custom-order-statuses-woocommerce'),
                                    render: (f) => <ToggleControl checked={false} onChange={() => {}} disabled __nextHasNoMarginBottom />
                                },
                                {
                                    name: 'statuses', defaultValue: [],
                                    label: __('Statuses', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', alignItems: 'center', marginLeft: '-32px' }}>
                                            <HelpTip message={__('Custom statuses to send emails. Leave blank to send emails on all custom statuses.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                            <Select
                                                isMulti
                                                options={statusOptions}
                                                value={[]}
                                                isDisabled
                                                placeholder={__('Select statuses…', 'custom-order-statuses-woocommerce')}
                                                classNamePrefix="cos-select"
                                            />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'interval_time', defaultValue: 1,
                                    label: __('Send after', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                            <HelpTip message={__('Send an alert after this many units of time.', 'custom-order-statuses-woocommerce') } className={'cos-select-helptip'}/>
                                            <NumberControl value={ADMIN_DISABLED_VALUES.interval_time} disabled />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'interval', defaultValue: 'days',
                                    label: __('Time unit', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                            <HelpTip message={__('Select time unit to send admin email everytime.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                            <SelectControl
                                                value={ADMIN_DISABLED_VALUES.interval}
                                                onChange={() => {}}
                                                options={[
                                                    { label: __('Minutes', 'custom-order-statuses-woocommerce'), value: 'minutes' },
                                                    { label: __('Hours', 'custom-order-statuses-woocommerce'), value: 'hours' },
                                                    { label: __('Days', 'custom-order-statuses-woocommerce'), value: 'days' },
                                                    { label: __('Weeks', 'custom-order-statuses-woocommerce'), value: 'weeks' },
                                                    { label: __('Months', 'custom-order-statuses-woocommerce'), value: 'months' },
                                                ]}
                                                disabled
                                                __nextHasNoMarginBottom
                                            />
                                        </div>
                                    )
                                },
                                {
                                    name: 'address', defaultValue: '',
                                    label: __('Email address', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', marginLeft: '-32px'}}>
                                            <HelpTip message={__('Comma separated list of emails. Leave blank to send emails to admin.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                            <InputControl value={ADMIN_DISABLED_VALUES.address} disabled placeholder={__('admin@example.com', 'custom-order-statuses-woocommerce')} />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'subject', defaultValue: '',
                                    label: __('Email subject', 'custom-order-statuses-woocommerce'),
                                    render: (f) => <InputControl value={ADMIN_DISABLED_VALUES.subject} disabled help={<ShortcodeHelp codes={ADMIN_CODES} />} />
                                },
                                {
                                    name: 'heading', defaultValue: '',
                                    label: __('Email heading', 'custom-order-statuses-woocommerce'),
                                    render: (f) => <InputControl value={ADMIN_DISABLED_VALUES.heading} disabled help={<ShortcodeHelp codes={ADMIN_CODES} />} />
                                },
                                {
                                    name: 'content', defaultValue: '',
                                    label: __('Email content', 'custom-order-statuses-woocommerce'),
                                    render: (f) => <TextareaControl value={ADMIN_DISABLED_VALUES.content} disabled rows={8} help={<ShortcodeHelp codes={ADMIN_CODES} />} __nextHasNoMarginBottom />
                                },
                            ]}
                        />
                    </div>

                    {/* ── Reset and Save buttons (only for Customer Email section) ── */}
                    <SettingsCard
                        heading={__('Reset Customer Email Settings', 'custom-order-statuses-woocommerce')}
                        control={emailForm.control}
                        fields={[{
                            name: '_reset', defaultValue: false,
                            render: () => (
                                <Button variant="secondary" type="button" onClick={() => setIsResetOpen(true)}>
                                    {__('Reset Customer Email Settings', 'custom-order-statuses-woocommerce')}
                                </Button>
                            ),
                        }]}
                    />

                    <ConfirmDialog
                        isOpen={isResetOpen}
                        cancelButtonText={__('Cancel', 'custom-order-statuses-woocommerce')}
                        confirmButtonText={__('Reset', 'custom-order-statuses-woocommerce')}
                        onCancel={() => setIsResetOpen(false)}
                        onConfirm={handleReset}
                    >
                        {__('Reset Customer Email settings to defaults?', 'custom-order-statuses-woocommerce')}
                    </ConfirmDialog>

                    <HStack spacing={3} expanded={false} justify="left">
                        <Button variant="primary" type="button" onClick={handleSave}>
                            {__('Save Changes', 'custom-order-statuses-woocommerce')}
                        </Button>
                    </HStack>
                </VStack>
            )}

            {showLoader && (
                <div className="cos_loader">
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            )}
        </VStack>
    );
}

export default withNotices(OrderStatusEmails);