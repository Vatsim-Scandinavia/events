import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { errorFor, type EventForm, type IntegrationField } from '@/components/events/event-form';

/**
 * The fields Integrations add to the event, such as the Discord channel its roster is shown in.
 * A secret that is set is never shown again: it can only be replaced or removed.
 */
export function IntegrationFieldsCard({ form, fields }: { form: EventForm; fields: IntegrationField[] }) {
    const { data } = form;

    if (fields.length === 0) {
        return null;
    }

    const setValue = (key: string, value: string) => form.setData('integration_fields', { ...data.integration_fields, [key]: value });
    const isRemoved = (key: string) => data.remove_integration_fields.includes(key);
    const setRemoved = (key: string, removed: boolean) =>
        form.setData((current) => ({
            ...current,
            integration_fields: { ...current.integration_fields, [key]: '' },
            remove_integration_fields: removed
                ? [...current.remove_integration_fields, key]
                : current.remove_integration_fields.filter((removedKey) => removedKey !== key),
        }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Integrations</CardTitle>
                <CardDescription>Details the Hub’s integrations need about this event.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {fields.map((field) => {
                    const error = errorFor(form, `integration_fields.${field.key}`);
                    const isSet = field.secret && field.status !== null;

                    return (
                        <div key={field.key} className="flex flex-col gap-2">
                            <Field label={field.label} error={error}>
                                <Input
                                    type={field.secret ? 'password' : 'text'}
                                    autoComplete="off"
                                    value={data.integration_fields[field.key] ?? (field.secret ? '' : (field.value ?? ''))}
                                    onChange={(e) => setValue(field.key, e.target.value)}
                                    disabled={isRemoved(field.key)}
                                    placeholder={isSet ? 'Saved; paste a new one to replace it' : undefined}
                                    aria-invalid={!!error}
                                />
                                {field.description && <span className="text-xs font-normal text-muted-foreground">{field.description}</span>}
                            </Field>
                            {isSet && (
                                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span>{isRemoved(field.key) ? 'Removed when you save.' : field.status}</span>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setRemoved(field.key, !isRemoved(field.key))}>
                                        {isRemoved(field.key) ? 'Keep it' : 'Remove'}
                                    </Button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
