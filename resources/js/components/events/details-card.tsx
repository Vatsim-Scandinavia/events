import { BannerInput } from '@/components/events/banner-input';
import { DescriptionInput } from '@/components/events/description-input';
import type { EventForm } from '@/components/events/event-form';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { Fir } from '@/types/inertia';

// The event's name, FIR, type, airports, description and banner.
export function DetailsCard({
    form,
    firs,
    types,
    bannerUrl,
}: {
    form: EventForm;
    firs: (Fir & { id: number })[];
    types: { id: number; name: string }[];
    bannerUrl: string | null;
}) {
    const { data, errors } = form;
    const firstError = (prefix: string) => Object.entries(errors).find(([key]) => key.startsWith(prefix))?.[1] as string | undefined;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                <div className="grid gap-5 sm:grid-cols-[1fr_14rem]">
                    <Field label="Name" error={errors.name}>
                        <Input value={data.name} onChange={(e) => form.setData('name', e.target.value)} aria-invalid={!!errors.name} required />
                    </Field>
                    <Field label="Run by" error={errors.fir_id}>
                        {/* Staff of a single FIR have nothing to choose. */}
                        {firs.length === 1 && data.fir_id === firs[0].id ? (
                            <span className="flex h-9 items-center font-medium">
                                {firs[0].icao} {firs[0].name !== firs[0].icao ? firs[0].name : ''}
                            </span>
                        ) : (
                            <NativeSelect
                                value={data.fir_id}
                                onChange={(e) => form.setData('fir_id', e.target.value === '' ? '' : Number(e.target.value))}
                                aria-invalid={!!errors.fir_id}
                                required
                            >
                                <option value="" disabled>
                                    {firs.length === 0 ? 'No FIRs set up yet' : 'Pick a FIR'}
                                </option>
                                {firs.map((fir) => (
                                    <option key={fir.id} value={fir.id}>
                                        {fir.icao} {fir.name !== fir.icao ? fir.name : ''}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </Field>
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label="Type" error={errors.event_type_id}>
                        <NativeSelect
                            value={data.event_type_id}
                            onChange={(e) => form.setData('event_type_id', e.target.value === '' ? '' : Number(e.target.value))}
                            aria-invalid={!!errors.event_type_id}
                        >
                            <option value="">General</option>
                            {types.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Field label="Airports" error={errors.airports ?? firstError('airports.')}>
                        <Input
                            value={data.airports}
                            onChange={(e) => form.setData('airports', e.target.value.toUpperCase())}
                            placeholder="EKCH EKBI"
                            aria-invalid={!!(errors.airports ?? firstError('airports.'))}
                            className="font-mono"
                        />
                    </Field>
                </div>
                <DescriptionInput
                    value={data.description}
                    error={errors.description}
                    onChange={(markdown) => form.setData('description', markdown)}
                />
                <BannerInput
                    current={data.remove_banner ? null : bannerUrl}
                    file={data.banner}
                    error={errors.banner}
                    onChange={(file) => form.setData('banner', file)}
                    onRemove={() => {
                        form.setData((values) => ({ ...values, banner: null, remove_banner: values.banner ? values.remove_banner : true }));
                    }}
                />
            </CardContent>
        </Card>
    );
}
