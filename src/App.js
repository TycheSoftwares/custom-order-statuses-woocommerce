/**
 * src/App.js
 *
 * Root component:
 *   - Card > CardHeader > CardBody > CardFooter layout
 *   - NavLink tabs with HashRouter paths
 *   - Routes/Route for screen rendering
 *   - VStack/HStack for layout
 *   - No global settings state — each screen manages its own via useForm
 */

import {
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
    ExternalLink,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Navigate, Route, Routes, NavLink } from 'react-router-dom';

import General           from './screens/General';
import OrderStatusRules  from './screens/OrderStatusRules';
import OrderStatusEmails from './screens/OrderStatusEmails';
import SMS               from './screens/SMS';
import Gateways          from './screens/Gateways';
import Labels            from './screens/Labels';
import StatusManager     from './screens/StatusManager';

const TABS = [
    { name: 'status-manager', title: __( 'Status Manager',          'custom-order-statuses-woocommerce' ), path: '/' },
    { name: 'general',     title: __( 'Settings',                    'custom-order-statuses-woocommerce' ), path: '/general' },
    { name: 'rules',       title: __( 'Rules',         'custom-order-statuses-woocommerce' ), path: '/rules' },
    { name: 'emails',      title: __( 'Emails',             'custom-order-statuses-woocommerce' ), path: '/emails' },
    { name: 'sms',         title: __( 'SMS',                        'custom-order-statuses-woocommerce' ), path: '/sms' },
    { name: 'gateways',    title: __( 'Status by Payments', 'custom-order-statuses-woocommerce' ), path: '/gateways' },
    { name: 'labels',      title: __( 'Labels',        'custom-order-statuses-woocommerce' ), path: '/labels' },
];

function App() {
    return (
        <Card>
            <CardHeader>
                <VStack spacing={ 2 }>
                    <Heading level={ 4 }>
                        { __( 'Custom Order Status for WooCommerce', 'custom-order-statuses-woocommerce' ) }
                    </Heading>
                    <Text>
                        { __( 'Create, manage and automate custom WooCommerce order statuses.', 'custom-order-statuses-woocommerce' ) }
                    </Text>
                </VStack>
            </CardHeader>

            <CardBody style={ { paddingTop: '0px' } }>
                <VStack>
                    { /* Tab navigation */ }
                    <HStack style={ { borderBottom: '1px solid #e5e5e5', flexWrap: 'wrap' } }>
                        <div className="cos-header-dashboard-tabs">
                            { TABS.map( ( tab ) => (
                                <NavLink
                                    key={ tab.name }
                                    to={ tab.path }
                                    className={ ( { isActive } ) =>
                                        'cos-dashboard-tab' + ( isActive ? ' is-active' : '' )
                                    }
                                    end={ tab.path === '/' }
                                >
                                    { tab.title }
                                </NavLink>
                            ) ) }
                        </div>
                    </HStack>

                    { /* Screen routing */ }
                    <Routes>
                        <Route path="/"                element={ <StatusManager /> } />
                        <Route path="/general"         element={ <General /> } />
                        <Route path="/rules"           element={ <OrderStatusRules /> } />
                        <Route path="/emails"          element={ <OrderStatusEmails /> } />
                        <Route path="/sms"             element={ <SMS /> } />
                        <Route path="/gateways"        element={ <Gateways /> } />
                        <Route path="/labels"          element={ <Labels /> } />
                        <Route path="*"                element={ <Navigate to="/" replace /> } />
                    </Routes>
                </VStack>
            </CardBody>

            <CardFooter justify="center">
                <VStack style={ { padding: '20px 0' } }>
                    <HStack justify="center" style={{ marginBottom: "22px" }}>
                        <ExternalLink href="https://support.tychesoftwares.com/help/2285384554/">
                            { __( 'Need support?', 'custom-order-statuses-woocommerce' ) }
                        </ExternalLink>
                        <Text style={ { fontWeight: 'bold' } }>
                            { __( "We're always happy to help you.", 'custom-order-statuses-woocommerce' ) }
                        </Text>
                    </HStack>
                    <HStack justify="center">
                        <Text>{ __( "If this plugin helped you,", 'custom-order-statuses-woocommerce' ) }</Text>
                        <ExternalLink href="https://wordpress.org/support/plugin/custom-order-statuses-woocommerce/reviews/" className="bogo-link">
                        { __( 'please rate it', 'custom-order-statuses-woocommerce' ) }
                        </ExternalLink>
                        <Text style={{ fontSize: "17px", color: "#FFBA00" }}>★★★★★</Text>
                    </HStack>
                </VStack>
            </CardFooter>
        </Card>
    );
}

export default App;
