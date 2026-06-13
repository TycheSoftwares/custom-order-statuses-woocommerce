import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Button, SelectControl, Spinner, withNotices,
    __experimentalConfirmDialog as ConfirmDialog,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    Card, CardHeader, CardBody,
    __experimentalHeading as Heading,
    __experimentalText as Text,
} from '@wordpress/components';
import { updateSettings, resetSection } from '../data/api';
import { useSettings } from '../context/SettingsContext';

function Gateways({ noticeOperations, noticeUI }) {
    const [showLoader, setShowLoader] = useState(false);
    const [isResetOpen, setIsResetOpen] = useState(false);
    const [paymentMethods, setPaymentMethods] = useState([]);
    const [orderStatuses, setOrderStatuses] = useState([]);
    const [values, setValues] = useState({});

    const { 
        settings, 
        options,
        isLoading: globalLoading,
        loadedSections,
        fetchSection,
        updateSettingsData 
    } = useSettings();

    const [isDataReady, setIsDataReady] = useState(() => {
        return loadedSections.settings && loadedSections.options;
    });

    useEffect(() => {
        if (settings?.gateways && loadedSections.settings) {
            setValues(settings.gateways);
        }
        if (options && loadedSections.options) {
            setPaymentMethods(options?.payment_methods ?? []);
            setOrderStatuses(options?.order_statuses ?? []);
        }
        if (loadedSections.settings && loadedSections.options && !isDataReady) {
        setIsDataReady(true);
    }
    }, [settings, options, loadedSections.settings, loadedSections.options, isDataReady]);

    useEffect(() => {
        if (!loadedSections.settings) {
            fetchSection('settings');
        }
        if (!loadedSections.options) {
            fetchSection('options');
        }
    }, []);

    const handleChange = (gatewayId, newStatus) => {
        setValues(prev => ({ ...prev, [gatewayId]: newStatus }));
    };

    const handleReset = async () => {
        setShowLoader(true);
        try {
            await resetSection('gateways');
            setValues({});
            
            const current = settings || {};
            updateSettingsData('settings', { ...current, gateways: {} });
            
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

    const handleSave = async () => {
        setShowLoader(true);
        try {
            const current = settings || {};
            await updateSettings({ ...current, gateways: values });
            updateSettingsData('settings', { ...current, gateways: values });
            
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

    const statusOptions = [
        { value: '', label: __('WooCommerce default', 'custom-order-statuses-woocommerce') },
        ...orderStatuses,
    ];

    const isLoading = globalLoading || (!isDataReady && (!loadedSections.settings || !loadedSections.options));

    return (
        <VStack style={{ marginTop: '30px' }}>
            {noticeUI}

            {isLoading ? (
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            ) : (
                <VStack className="cos_setting_section" spacing={10}>
                    <Card>
                        <CardHeader>
                            <VStack spacing={2}>
                                <Heading level={4}>
                                    {__('Default Status by Payment Gateway', 'custom-order-statuses-woocommerce')}
                                </Heading>
                                <Text className="components-text">
                                    {__('Applied right after payment is confirmed.', 'custom-order-statuses-woocommerce')}
                                </Text>
                            </VStack>
                        </CardHeader>
                        <CardBody>
                            {paymentMethods.length === 0 ? (
                                <Text variant="muted">
                                    {__('No payment gateways found.', 'custom-order-statuses-woocommerce')}
                                </Text>
                            ) : (
                                <table className="cos-settings-table">
                                    <colgroup>
                                        <col className="cos-settings-table__label-col" />
                                        <col className="cos-settings-table__field-col" />
                                    </colgroup>
                                    <tbody>
                                        {paymentMethods.map((gw) => (
                                            <tr key={gw.value} className="cos-settings-table__row">
                                                <td className="cos-settings-table__label">
                                                    <Text>{gw.label}</Text>
                                                </td>
                                                <td className="cos-settings-table__field">
                                                    <SelectControl
                                                        value={values[gw.value] ?? ''}
                                                        options={statusOptions}
                                                        onChange={(val) => handleChange(gw.value, val)}
                                                        __nextHasNoMarginBottom
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <VStack spacing={2}>
                                <Heading level={4}>{__('Reset Settings', 'custom-order-statuses-woocommerce')}</Heading>
                            </VStack>
                        </CardHeader>
                        <CardBody>
                            <table className="cos-settings-table">
                                <colgroup>
                                    <col className="cos-settings-table__label-col" />
                                    <col className="cos-settings-table__field-col" />
                                </colgroup>
                                <tbody>
                                    <tr className="cos-settings-table__row">
                                        <td className="cos-settings-table__field">
                                            <Button variant="secondary" type="button" onClick={() => setIsResetOpen(true)}>
                                                {__('Reset Settings', 'custom-order-statuses-woocommerce')}
                                            </Button>
                                            <ConfirmDialog
                                                isOpen={isResetOpen}
                                                cancelButtonText={__('Cancel', 'custom-order-statuses-woocommerce')}
                                                confirmButtonText={__('Reset', 'custom-order-statuses-woocommerce')}
                                                onCancel={() => setIsResetOpen(false)}
                                                onConfirm={handleReset}
                                            >
                                                {__('Reset all gateway status settings to defaults?', 'custom-order-statuses-woocommerce')}
                                            </ConfirmDialog>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardBody>
                    </Card>

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

export default withNotices(Gateways);