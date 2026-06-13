/**
 * src/screens/Labels.js
 * Free version – Rename Order Status Labels is Pro only, all fields disabled.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Spinner, withNotices,
    __experimentalVStack as VStack,
    __experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useForm } from 'react-hook-form';
import SettingsCard from '../components/SettingsCard';
import ProNotice from '../components/ProNotice';
import { useSettings } from '../context/SettingsContext';

function Labels({ noticeOperations, noticeUI }) {
    const [orderStatuses, setOrderStatuses] = useState([]);

    const { 
        options,
        isLoading: globalLoading,
        loadedSections,
        fetchSection,
    } = useSettings();

    const [isFormReady, setIsFormReady] = useState(() => {
        return loadedSections.options;
    });

    // Static empty values – no editing allowed
    const { control } = useForm({ defaultValues: {} });

    useEffect(() => {
        if (options && loadedSections.options) {
            setOrderStatuses(options?.default_order_statuses ?? []);
        }
        if (loadedSections.options && !isFormReady) {
            setIsFormReady(true);
        }
    }, [options, loadedSections.options, isFormReady]);

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
                    <ProNotice feature={__('Rename Order Status Labels', 'custom-order-statuses-woocommerce')} />

                    <SettingsCard
                        className="cos_labels_setting_section"
                        heading={__('Rename Order Status Labels', 'custom-order-statuses-woocommerce')}
                        subHeading={__('Renames the displayed label only. Slugs stay the same.', 'custom-order-statuses-woocommerce')}
                        control={control}
                        fields={orderStatuses.map((status) => ({
                            name: status.value,
                            defaultValue: '',
                            label: status.label,
                            render: (f) => (
                                <InputControl
                                    value={''}
                                    onChange={() => {}}
                                    disabled
                                    placeholder={status.label}
                                />
                            ),
                        }))}
                    />

                    {/* No Save or Reset buttons in free version */}
                </VStack>
            )}
        </VStack>
    );
}

export default withNotices(Labels);