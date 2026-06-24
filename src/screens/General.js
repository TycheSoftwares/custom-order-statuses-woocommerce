/**
 * src/screens/General.js
 * Fixed - Prevents flash of default values by using form values directly
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button,
    ToggleControl,
    SelectControl,
    __experimentalHeading as Heading,
    __experimentalText as Text,
    __experimentalNumberControl as NumberControl,
    Spinner,
    withNotices,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import { useForm } from 'react-hook-form';
import SettingsCard from '../components/SettingsCard';
import HelpTip from '../components/HelpTip';
import ProNotice, { ProInlineNotice } from '../components/ProNotice';
import { updateSettings, resetSection } from '../data/api';
import { useSettings } from '../context/SettingsContext';

function General({ noticeOperations, noticeUI }) {
    const [showLoader, setShowLoader] = useState(false);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isTrackingDialogOpen, setIsTrackingDialogOpen] = useState(false);
    const [orderStatuses, setOrderStatuses] = useState([]);

    // Use global settings context
    const { 
        settings, 
        options,
        isLoading: globalLoading, 
        updateSettingsData,
        fetchSection,
        loadedSections 
    } = useSettings();

    // Initialize isFormReady based on whether data is already loaded
    const [isFormReady, setIsFormReady] = useState(() => {
        // If settings are already loaded in context, form is ready immediately
        return loadedSections.settings && loadedSections.options;
    });

    // Get the current values from settings
    const currentValues = settings?.general || {};

    const { control, handleSubmit, reset, watch } = useForm({
        defaultValues: {
            add_to_bulk_actions: true,
            add_to_reports: true,
            default_status: '',
            fallback_delete_status: 'on-hold',
            add_to_order_list_actions: false,
            list_actions_colored: false,
            enable_column_colored: false,
            enable_column_icons: true,
            add_to_order_preview_actions: false,
            enable_editable: false,
            enable_paid: false,
            enable_fallback: false,
            filters_priority: 0,
            ...currentValues, // This merges saved values immediately
        },
    });

    const showListColors = watch('add_to_order_list_actions');

    // Load order statuses options from context when available
    useEffect(() => {
        if (options && loadedSections.options) {
            const statuses = options.order_statuses || [];
            setOrderStatuses(statuses);
        }
    }, [options, loadedSections.options]);

    // Reset form when settings change (for updates after save)
    useEffect(() => {
        if (settings?.general && loadedSections.settings) {
            reset(settings.general);
        }
        // Mark form as ready after initial reset or if data is already there
        if ((settings?.general && loadedSections.settings) || (loadedSections.settings && !settings?.general)) {
            setIsFormReady(true);
        }
    }, [settings, loadedSections.settings, reset]);

    // Fetch data if not already loaded (only on initial mount)
    useEffect(() => {
        if (!loadedSections.settings) {
            fetchSection('settings');
        }
        if (!loadedSections.options) {
            fetchSection('options');
        }
    }, []);

    const onSubmit = async (data) => {
        setShowLoader(true);
        try {
            const current = settings || {};
            await updateSettings({ ...current, general: data });
            
            // Update global settings
            updateSettingsData('settings', { ...current, general: data });
            
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __('Settings saved successfully.', 'custom-order-statuses-woocommerce')
            });
        } catch (e) {
            noticeOperations.createNotice({
                status: 'error',
                content: __('Error saving settings.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
        }
    };

    const onReset = async () => {
        setShowLoader(true);
        try {
            const defaults = await resetSection('general');
            reset(defaults);
            
            // Update global settings with defaults
            const current = settings || {};
            updateSettingsData('settings', { ...current, general: defaults });
            
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice({
                status: 'success',
                content: __('Settings reset to defaults.', 'custom-order-statuses-woocommerce')
            });
        } catch (e) {
            noticeOperations.createNotice({
                status: 'error',
                content: __('Reset failed.', 'custom-order-statuses-woocommerce')
            });
        } finally {
            setShowLoader(false);
            setIsDialogOpen(false);
        }
    };

    const resetTracking = () => {
        setShowLoader(true);
        dispatch(coreDataStore)
            .saveEntityRecord('root', 'site', {
                cos_allow_tracking: '',
                ts_tracker_last_send: '',
            })
            .then(() => {
                noticeOperations.removeAllNotices();
                noticeOperations.createNotice({
                    status: 'success',
                    content: __('Tracking has been successfully reset.', 'custom-order-statuses-woocommerce'),
                });
            })
            .catch(() => {
                noticeOperations.createNotice({
                    status: 'error',
                    content: __('Failed to reset tracking.', 'custom-order-statuses-woocommerce'),
                });
            })
            .finally(() => {
                setShowLoader(false);
                setIsTrackingDialogOpen(false);
            });
    };

    const statusOptions = [
        { value: '', label: __('No changes (WooCommerce default)', 'custom-order-statuses-woocommerce') },
        ...orderStatuses,
    ];

    // Show loader until we have the data AND form is ready
    const isLoading = globalLoading || (!isFormReady && (!loadedSections.settings || !loadedSections.options));

    return (
        <VStack style={{ marginTop: '30px' }}>
            {noticeUI}

            {isLoading ? (
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            ) : (
                <form onSubmit={handleSubmit(onSubmit)}>
                    <VStack className="cos_setting_section" spacing={10}>
                        {/* Rest of your form remains the same */}
                        <SettingsCard
                            heading={__('Order Status Defaults', 'custom-order-statuses-woocommerce')}
                            subHeading={__('Choose default and fallback order statuses', 'custom-order-statuses-woocommerce')}
                            className="cos-general-settings"
                            control={control}
                            fields={[
                                {
                                    name: 'default_status', defaultValue: '',
                                    label: __('Default order status', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={__('You can change the default order status here. However some payment gateways may change this status immediately on order creation. E.g. BACS gateway will change status to On-hold.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'} />
                                            <SelectControl
                                                value={f.value}
                                                options={statusOptions}
                                                onChange={f.onChange}
                                                help={__('Set per-gateway defaults in the Status by Payments tab.', 'custom-order-statuses-woocommerce')}
                                                __nextHasNoMarginBottom
                                                style={{ flex: 1 }}
                                            />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'enable_fallback', defaultValue: false,
                                    label: __( 'Apply fallback status when plugin is disabled', 'custom-order-statuses-woocommerce' ),
                                    render: () => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <div style={{ opacity: 0.5, pointerEvents: 'none' }}>
                                                <ToggleControl
                                                    checked={ false }
                                                    onChange={ () => {} }
                                                    __nextHasNoMarginBottom
                                                />
                                            </div>
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'fallback_delete_status', defaultValue: 'on-hold',
                                    label: __('Status when a custom status is deleted', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={__('When you delete some custom status, all orders with that status will be updated to this fallback status. Please note that all fallback status triggers (email etc.) will be activated.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'} />
                                            <SelectControl
                                                value={f.value}
                                                options={orderStatuses}
                                                onChange={f.onChange}
                                                help={__('Used when a custom status is removed.', 'custom-order-statuses-woocommerce')}
                                                __nextHasNoMarginBottom
                                            />
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <SettingsCard
                            className="cos-general-settings"
                            heading={__('Display Settings', 'custom-order-statuses-woocommerce')}
                            subHeading={__('Control how custom statuses appear across your store', 'custom-order-statuses-woocommerce')}
                            control={control}
                            fields={[
                                {
                                    name: 'add_to_bulk_actions', defaultValue: true,
                                    label: __('Show in bulk actions', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={!!f.value} onChange={f.onChange} __nextHasNoMarginBottom />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'add_to_reports', defaultValue: true,
                                    label: __('Show in reports', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={!!f.value} onChange={f.onChange} __nextHasNoMarginBottom />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'add_to_order_list_actions', defaultValue: false,
                                    label: __('Show in order list actions', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={ false } disabled onChange={f.onChange} __nextHasNoMarginBottom />
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'list_actions_colored', defaultValue: false,
                                    label: __('Show colors for action buttons', 'custom-order-statuses-woocommerce'),
                                    showWhen: !!showListColors,
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={ false } disabled onChange={f.onChange} __nextHasNoMarginBottom />
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'add_to_order_preview_actions', defaultValue: false,
                                    label: __('Show in order preview actions', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={ false } disabled onChange={f.onChange} __nextHasNoMarginBottom />
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'enable_column_colored', defaultValue: false,
                                    label: __('Show colors in status column', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={!!f.value} onChange={f.onChange} __nextHasNoMarginBottom />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'enable_column_icons', defaultValue: true,
                                    label: __('Show icons in status column', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <ToggleControl checked={!!f.value} onChange={f.onChange} __nextHasNoMarginBottom />
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <SettingsCard
                            heading={__('Advanced Options', 'custom-order-statuses-woocommerce')}
                            subHeading={__('Fine-tune how custom statuses behave across your store', 'custom-order-statuses-woocommerce')}
                            className="cos-general-settings"
                            control={control}
                            fields={[
                                {
                                    name: '_subheading2',
                                    defaultValue: false,
                                    render: () => (
                                        <div style={{ 
                                            margin: '16px 0 8px 0', 
                                            paddingBottom: '16px',
                                            borderBottom: '1px solid #e0e0e0',
                                            width: '100%'
                                        }}>
                                            <Heading level={5} style={{ fontSize: '14px', fontWeight: 600, margin: 0 }}>
                                                {__('Order Editing & Payments', 'custom-order-statuses-woocommerce')}
                                            </Heading>
                                        </div>
                                    ),
                                },
                                {
                                    name: 'enable_editable', defaultValue: false,
                                    label: __( 'Allow editing of orders with this status', 'custom-order-statuses-woocommerce' ),
                                    render: () => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <div style={{ opacity: 0.5, pointerEvents: 'none' }}>
                                                <ToggleControl
                                                    checked={ false }
                                                    onChange={ () => {} }
                                                    __nextHasNoMarginBottom
                                                />
                                            </div>
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: 'enable_paid', defaultValue: false,
                                    label: __( 'Mark orders with this status as paid', 'custom-order-statuses-woocommerce' ),
                                    render: () => (
                                        <div style={{ marginLeft: '40px' }}>
                                            <div style={{ opacity: 0.5, pointerEvents: 'none' }}>
                                                <ToggleControl
                                                    checked={ false }
                                                    onChange={ () => {} }
                                                    help={ __( 'Default paid statuses: Processing, Completed.', 'custom-order-statuses-woocommerce' ) }
                                                    __nextHasNoMarginBottom
                                                />
                                            </div>
                                            <ProInlineNotice />
                                        </div>
                                    ),
                                },
                                {
                                    name: '_subheading3',
                                    defaultValue: false,
                                    render: () => (
                                        <div style={{ 
                                            margin: '16px 0 8px 0', 
                                            paddingBottom: '16px',
                                            borderBottom: '1px solid #e0e0e0',
                                            width: '100%'
                                        }}>
                                            <Heading level={5} style={{ fontSize: '14px', fontWeight: 600, margin: 0 }}>
                                                {__('Filters Priority', 'custom-order-statuses-woocommerce')}
                                            </Heading>
                                        </div>
                                    ),
                                },
                                {
                                    name: 'filters_priority', defaultValue: 0,
                                    label: __('Priority', 'custom-order-statuses-woocommerce'),
                                    render: (f) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={__('Sets priority for WooCommerce filters used in this plugin. Leave at 0 if unsure.', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
                                            <NumberControl
                                                value={f.value}
                                                onChange={f.onChange}
                                                min={0}
                                                step={1}
                                            />
                                        </div>
                                    ),
                                },

                            ]}
                        />

                        <SettingsCard
                            heading={__('Reset Settings', 'custom-order-statuses-woocommerce')}
                            control={control}
                            className="cos-general-settings"
                            fields={[
                                {
                                    name: '_reset', defaultValue: false,
                                    render: () => (
                                        <>
                                            <Button variant="secondary" onClick={() => setIsDialogOpen(true)}>
                                                {__('Reset Settings', 'custom-order-statuses-woocommerce')}
                                            </Button>
                                            <ConfirmDialog
                                                isOpen={isDialogOpen}
                                                cancelButtonText={__('Cancel', 'custom-order-statuses-woocommerce')}
                                                confirmButtonText={__('Reset', 'custom-order-statuses-woocommerce')}
                                                onCancel={() => setIsDialogOpen(false)}
                                                onConfirm={onReset}
                                            >
                                                {__('Are you sure you want to reset General settings to defaults?', 'custom-order-statuses-woocommerce')}
                                            </ConfirmDialog>
                                        </>
                                    ),
                                },
                                {
                                    name: 'ts_reset_tracking', defaultValue: false,
                                    render: () => (
                                        <>
                                            <Button variant="secondary" onClick={() => setIsTrackingDialogOpen(true)}>
                                                {__('Reset Usage Tracking', 'custom-order-statuses-woocommerce')}
                                            </Button>
                                            <ConfirmDialog
                                                isOpen={isTrackingDialogOpen}
                                                cancelButtonText={__('Cancel', 'custom-order-statuses-woocommerce')}
                                                confirmButtonText={__('Reset', 'custom-order-statuses-woocommerce')}
                                                onCancel={() => setIsTrackingDialogOpen(false)}
                                                onConfirm={resetTracking}
                                            >
                                                {__('Are you sure you want to reset all usage tracking data?', 'custom-order-statuses-woocommerce')}
                                            </ConfirmDialog>
                                        </>
                                    ),
                                },
                            ]}
                        />

                        <HStack spacing={3} expanded={false} justify="left">
                            <Button variant="primary" type="submit">
                                {__('Save Changes', 'custom-order-statuses-woocommerce')}
                            </Button>
                        </HStack>

                    </VStack>
                </form>
            )}

            {showLoader && (
                <div className="cos_loader">
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            )}
        </VStack>
    );
}

export default withNotices(General);