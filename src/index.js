/**
 * src/index.js
 */
import { createRoot } from 'react-dom/client';
import { HashRouter } from 'react-router-dom';
import App from './App';
import { SettingsProvider } from './context/SettingsContext';
import './app.scss';

window.addEventListener(
    'load',
    function () {
        const container = document.querySelector('#cos-settings-root');
        if (!container) {
            console.error('[COS Pro] #cos-settings-root not found.');
            return;
        }
        const root = createRoot(container);
        root.render(
            <SettingsProvider>
                <HashRouter>
                    <App />
                </HashRouter>
            </SettingsProvider>
        );
    },
    false
);
