/**
 * src/screens/SMS.js
 * Free version – SMS feature is Pro only, all fields disabled.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    ToggleControl, Spinner, withNotices, TextareaControl,
    __experimentalVStack as VStack,
    __experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useForm } from 'react-hook-form';
import Select from 'react-select';
import SettingsCard from '../components/SettingsCard';
import HelpTip from '../components/HelpTip';
import ShortcodeHelp from '../components/ShortcodeHelp';
import ProNotice from '../components/ProNotice';
import { useSettings } from '../context/SettingsContext';

const SMS_CODES = ['order_id','order_number','order_date','first_name','last_name',
    'site_title','status_from','status_to','billing_address','shipping_address','product_titles'];

// Static disabled values
const DISABLED_VALUES = {
    enabled: false,
    from_num: '',
    account_sid: '',
    auth_token: '',
    statuses: [],
    content: '',
};

function SMS({ noticeOperations, noticeUI }) {
    const [statusOptions, setStatusOptions] = useState([]);

    const { 
        options,
        isLoading: globalLoading,
        loadedSections,
        fetchSection,
    } = useSettings();

    const [isFormReady, setIsFormReady] = useState(() => {
        return loadedSections.options;
    });

    const { control } = useForm({ defaultValues: DISABLED_VALUES });

    // Load options for select dropdowns (for display only)
    useEffect(() => {
        if (options && loadedSections.options) {
            const statuses = (options?.custom_order_statuses ?? []).map(status => ({
                ...status,
                value: status.value.startsWith('wc-')
                    ? status.value
                    : `wc-${status.value}`
            }));
            setStatusOptions(statuses);
        }
    }, [options, loadedSections.options]);

    useEffect(() => {
        if (loadedSections.options && !isFormReady) {
            setIsFormReady(true);
        }
    }, [loadedSections.options, isFormReady]);

    useEffect(() => {
        if (!loadedSections.options) {
            fetchSection('options');
        }
    }, []);

    const isLoading = globalLoading || !loadedSections.options || !isFormReady;

    return (
        <VStack style={{ marginTop: '30px' }}>
            {noticeUI}

            {isLoading ? (
                <div style={{ padding: '40px', textAlign: 'center' }}>
                    <Spinner style={{ width: '30px', height: '30px' }} />
                </div>
            ) : (
                <VStack className="cos_setting_section" spacing={10}>
                    <ProNotice feature={__('SMS Notifications', 'custom-order-statuses-woocommerce')} />

                    <SettingsCard
                        heading={__('SMS Settings', 'custom-order-statuses-woocommerce')}
                        subHeading={__('Send SMS notifications via Twilio when order statuses change.', 'custom-order-statuses-woocommerce')}
                        control={control}
                        fields={[
                            { 
                                name: 'enabled', defaultValue: false, 
                                label: __('Enable SMS notifications', 'custom-order-statuses-woocommerce'),
                                render: (f) => <ToggleControl checked={false} onChange={() => {}} disabled __nextHasNoMarginBottom /> 
                            },
                            { 
                                name: 'from_num', defaultValue: '', 
                                label: __('Sender', 'custom-order-statuses-woocommerce'),
                                render: (f) => <InputControl value={DISABLED_VALUES.from_num} disabled placeholder={__('+1234567890', 'custom-order-statuses-woocommerce')} /> 
                            },
                            { 
                                name: 'account_sid', defaultValue: '', 
                                label: __('Twilio Account SID', 'custom-order-statuses-woocommerce'),
                                render: (f) => <InputControl value={DISABLED_VALUES.account_sid} disabled placeholder={__('ACxxxxxxxxxxxxxxxxxxxx', 'custom-order-statuses-woocommerce')} /> 
                            },
                            { 
                                name: 'auth_token', defaultValue: '', 
                                label: __('Twilio Auth Token', 'custom-order-statuses-woocommerce'),
                                render: (f) => <InputControl value={DISABLED_VALUES.auth_token} disabled type="password" placeholder={__('••••••••••••••••', 'custom-order-statuses-woocommerce')} /> 
                            },
                            { 
                                name: 'statuses', defaultValue: [], 
                                label: __('Apply to statuses', 'custom-order-statuses-woocommerce'),
                                render: (f) => (
                                    <div style={{ display: 'flex', marginLeft: '-32px' }}>
                                        <HelpTip message={__('Custom statuses to send SMS. All statuses (leave blank).', 'custom-order-statuses-woocommerce')} className={'cos-select-helptip'}/>
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
                                name: 'content', defaultValue: '',
                                label: __('Message', 'custom-order-statuses-woocommerce'),
                                render: (f) => (
                                    <>
                                        <TextareaControl 
                                            value={DISABLED_VALUES.content} 
                                            onChange={() => {}} 
                                            disabled
                                            multiLine 
                                            help={<ShortcodeHelp codes={SMS_CODES} />} 
                                        />
                                    </>
                                ) 
                            },
                        ]}
                    />

                    {/* No Save or Reset buttons in free version */}
                </VStack>
            )}
        </VStack>
    );
}

export default withNotices(SMS);