import { Plus, X } from 'lucide-react';
import { errorFor, type EventForm } from '@/components/events/event-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { EventRoute } from '@/types/event';

// Suggested routes pilots can copy from the event page. newKey gives each added row a key of its own.
export function RoutesCard({ form, newKey }: { form: EventForm; newKey: () => number }) {
    const { data } = form;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Suggested routes</CardTitle>
                <CardDescription>Pilots can copy these from the event page.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {data.routes.map((route, index) => {
                    const set = (change: Partial<EventRoute>) =>
                        form.setData(
                            'routes',
                            data.routes.map((item) => (item.key === route.key ? { ...item, ...change } : item)),
                        );
                    const rowError =
                        errorFor(form, `routes.${index}.from`) ?? errorFor(form, `routes.${index}.to`) ?? errorFor(form, `routes.${index}.route`);

                    return (
                        <div key={route.key} className="flex flex-col gap-2 rounded-lg p-3 ring-1 ring-foreground/10">
                            <div className="flex items-center gap-2">
                                <Input
                                    aria-label="From"
                                    placeholder="From"
                                    className="w-24 font-mono"
                                    maxLength={4}
                                    value={route.from}
                                    onChange={(e) => set({ from: e.target.value.toUpperCase() })}
                                />
                                <span className="text-muted-foreground">→</span>
                                <Input
                                    aria-label="To"
                                    placeholder="To"
                                    className="w-24 font-mono"
                                    maxLength={4}
                                    value={route.to}
                                    onChange={(e) => set({ to: e.target.value.toUpperCase() })}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="ml-auto"
                                    aria-label="Remove route"
                                    onClick={() =>
                                        form.setData(
                                            'routes',
                                            data.routes.filter((item) => item.key !== route.key),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                            <Textarea
                                aria-label="Route"
                                placeholder="TUDLO DCT ..."
                                className="min-h-10 font-mono text-xs"
                                value={route.route}
                                onChange={(e) => set({ route: e.target.value.toUpperCase() })}
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={!!route.mandatory} onCheckedChange={(checked) => set({ mandatory: checked === true })} />
                                Mandatory route
                            </label>
                            <FieldError message={rowError} />
                        </div>
                    );
                })}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="self-start"
                    onClick={() => form.setData('routes', [...data.routes, { key: newKey(), from: '', to: '', route: '', mandatory: false }])}
                >
                    <Plus />
                    Add a route
                </Button>
            </CardContent>
        </Card>
    );
}
