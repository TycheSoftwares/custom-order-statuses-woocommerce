/**
 * src/context/SettingsContext.js
 * Global settings state to avoid repeated API calls on tab switches
 */

import { createContext, useContext, useState, useEffect, useCallback } from '@wordpress/element';
import { getSettings, getRules, getStatuses, getOptions } from '../data/api';

const SettingsContext = createContext();

export const useSettings = () => {
    const context = useContext(SettingsContext);
    if (!context) {
        throw new Error('useSettings must be used within SettingsProvider');
    }
    return context;
};

export const SettingsProvider = ({ children }) => {
    const [settings, setSettings] = useState({});
    const [rules, setRules] = useState([]);
    const [statuses, setStatuses] = useState([]);
    const [options, setOptions] = useState({});
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    
    // Track which sections have been loaded
    const [loadedSections, setLoadedSections] = useState({
        settings: false,
        rules: false,
        statuses: false,
        options: false,
    });

    // Fetch all data on initial load
    const fetchAllData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            // Fetch all required data in parallel
            const [settingsData, rulesData, statusesData, optionsData] = await Promise.allSettled([
                getSettings(),
                getRules(),
                getStatuses(),
                getOptions(),
            ]);

            // Handle settings
            if (settingsData.status === 'fulfilled') {
                setSettings(settingsData.value || {});
                setLoadedSections(prev => ({ ...prev, settings: true }));
            } else {
                console.error('Failed to load settings:', settingsData.reason);
                setSettings({});
                setLoadedSections(prev => ({ ...prev, settings: true }));
            }

            // Handle rules
            if (rulesData.status === 'fulfilled') {
                setRules(rulesData.value || []);
                setLoadedSections(prev => ({ ...prev, rules: true }));
            } else {
                console.error('Failed to load rules:', rulesData.reason);
                setRules([]);
                setLoadedSections(prev => ({ ...prev, rules: true }));
            }

            // Handle statuses
            if (statusesData.status === 'fulfilled') {
                setStatuses(statusesData.value || []);
                setLoadedSections(prev => ({ ...prev, statuses: true }));
            } else {
                console.error('Failed to load statuses:', statusesData.reason);
                setStatuses([]);
                setLoadedSections(prev => ({ ...prev, statuses: true }));
            }

            // Handle options
            if (optionsData.status === 'fulfilled') {
                const optionsValue = optionsData.value || {};
                setOptions(optionsValue);
                setLoadedSections(prev => ({ ...prev, options: true }));
            } else {
                console.error('Failed to load options:', optionsData.reason);
                setOptions({});
                setLoadedSections(prev => ({ ...prev, options: true }));
            }
        } catch (err) {
            setError(err.message || 'Failed to load settings');
            console.error('Settings context error:', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Fetch only specific section data
    const fetchSection = useCallback(async (section) => {
        try {
            switch (section) {
                case 'settings':
                    if (!loadedSections.settings) {
                        const data = await getSettings();
                        setSettings(data || {});
                        setLoadedSections(prev => ({ ...prev, settings: true }));
                    }
                    break;
                case 'rules':
                    if (!loadedSections.rules) {
                        const data = await getRules();
                        setRules(data || []);
                        setLoadedSections(prev => ({ ...prev, rules: true }));
                    }
                    break;
                case 'statuses':
                    if (!loadedSections.statuses) {
                        const data = await getStatuses();
                        setStatuses(data || []);
                        setLoadedSections(prev => ({ ...prev, statuses: true }));
                    }
                    break;
                case 'options':
                    if (!loadedSections.options) {
                        const data = await getOptions();
                        setOptions(data || {});
                        setLoadedSections(prev => ({ ...prev, options: true }));
                    }
                    break;
                default:
                    break;
            }
        } catch (err) {
            console.error(`Failed to fetch ${section}:`, err);
            setError(err.message);
            // Mark as loaded even on error to prevent infinite loading
            setLoadedSections(prev => ({ ...prev, [section]: true }));
        }
    }, [loadedSections]);

    // Update settings after save
    const updateSettingsData = useCallback((section, data) => {
        switch (section) {
            case 'settings':
                setSettings(prev => ({ ...prev, ...data }));
                break;
            case 'rules':
                setRules(data);
                break;
            case 'statuses':
                setStatuses(data);
                break;
            case 'options':
                setOptions(data);
                break;
            default:
                break;
        }
    }, []);

    // Refresh all data
    const refreshAll = useCallback(async () => {
        setLoadedSections({
            settings: false,
            rules: false,
            statuses: false,
            options: false,
        });
        await fetchAllData();
    }, [fetchAllData]);

    /**
     * Refresh a single section by fetching directly from the API.
     * Does NOT go through fetchSection so there is no stale-closure
     * issue with loadedSections. Safe to call after any save/delete.
     */
    const refreshSection = useCallback(async (section) => {
        try {
            switch (section) {
                case 'settings': {
                    const data = await getSettings();
                    setSettings(data || {});
                    setLoadedSections(prev => ({ ...prev, settings: true }));
                    break;
                }
                case 'rules': {
                    const data = await getRules();
                    setRules(data || []);
                    setLoadedSections(prev => ({ ...prev, rules: true }));
                    break;
                }
                case 'statuses': {
                    const data = await getStatuses();
                    setStatuses(Array.isArray(data) ? data : []);
                    setLoadedSections(prev => ({ ...prev, statuses: true }));
                    break;
                }
                case 'options': {
                    const data = await getOptions();
                    setOptions(data || {});
                    setLoadedSections(prev => ({ ...prev, options: true }));
                    break;
                }
                default:
                    break;
            }
        } catch (err) {
            console.error(`Failed to refresh ${section}:`, err);
            setError(err.message);
        }
    }, []); // No dependencies — calls API functions directly, never reads stale state

    

    // Initial load
    useEffect(() => {
        fetchAllData();
    }, [fetchAllData]);

    const value = {
        // Data - always provide default values
        settings: settings || {},
        rules: rules || [],
        statuses: statuses || [],
        options: options || {},
        isLoading,
        error,
        loadedSections,
        
        // Actions
        fetchSection,
        updateSettingsData,
        refreshAll,
        refreshSection,
        
        // Helper functions with safe defaults
        getGeneralSettings: () => settings?.general || {},
        getRulesList: () => rules || [],
        getStatusesList: () => statuses || [],
        getOptions: () => options || {},
    };

    return (
        <SettingsContext.Provider value={value}>
            {children}
        </SettingsContext.Provider>
    );
};