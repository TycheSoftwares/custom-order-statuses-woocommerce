/**
 * src/components/SettingsCard.js
 *
 * Card > CardHeader > CardBody with a two-column fixed-layout table.
 *
 * Column widths (consistent across all screens):
 *   Label  : 220px fixed  — never shrinks or grows with content
 *   Field  : remaining space, max 480px — inputs stay a readable width
 *
 * On tablet (≤782px) the columns stack vertically via CSS.
 */

import {
    Card,
    CardHeader,
    CardBody,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalHeading as Heading,
    __experimentalText as Text,
} from '@wordpress/components';
import { Controller } from 'react-hook-form';

const SettingsCard = ( {
    heading,
    headingExtra,
    subHeading = null,
    fields     = [],
    display    = true,
    control,
    className  = '',
} ) => {
    if ( ! display ) return null;

    return (
        <Card className={ className }>
            <CardHeader>
                <VStack spacing={ 2 }>
                    <HStack spacing={2} wrap={true}>
                        <Heading level={4}>{heading}</Heading>
                    </HStack>
                    {headingExtra && headingExtra}
                    { subHeading && (
                        <Text className="components-text">{ subHeading }</Text>
                    ) }
                </VStack>
            </CardHeader>

            <CardBody>
                <table className="cos-settings-table">
                    <colgroup>
                        <col className="cos-settings-table__label-col" />
                        <col className="cos-settings-table__field-col" />
                    </colgroup>
                    <tbody>
                        { fields.map( ( field, index ) =>
                            ( field.showWhen === undefined || field.showWhen ) ? (
                                <tr key={ index } className="cos-settings-table__row">
                                    { field.label ? (
                                        <td className="cos-settings-table__label ">
                                            <Text className={'cos-settings-label'}>{ field.label }</Text>
                                        </td>
                                    ) : (
                                        // No label — field spans both columns
                                        null
                                    ) }
                                    <td
                                        className="cos-settings-table__field"
                                        colSpan={ field.label ? 1 : 2 }
                                    >
                                        <Controller
                                            name={ field.name }
                                            control={ control }
                                            defaultValue={ field.defaultValue }
                                            rules={ field.rules }
                                            render={ ( { field: controllerField, fieldState: { error } } ) =>
                                                field.render( controllerField, error )
                                            }
                                        />
                                    </td>
                                </tr>
                            ) : null
                        ) }
                    </tbody>
                </table>
            </CardBody>
        </Card>
    );
};

export default SettingsCard;
