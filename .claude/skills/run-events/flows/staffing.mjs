// Every seeded event is a Draft. Event 4 (Copenhagen Fly-in) takes shift bookings and event 2
// (Scandinavian Night Ops) takes open interest. Needs a fresh database (serve.sh): it publishes.
const publish = async (app, id) => {
    await app.go(`/events/${id}`);
    await app.page.getByRole('button', { name: 'Publish' }).click();
    await app.page.getByRole('alertdialog').getByRole('button').last().click();
    await app.settle();
    const drafts = await app.page.getByText('Draft', { exact: true }).count();
    app.check(`event ${id} published`, `${drafts} Draft badges`, drafts === 0);
};

export default async function (app) {
    await app.signIn('Hub Admin');
    await publish(app, 4);
    await publish(app, 2);

    // Planner: shorten the first shift with the keyboard, then save.
    await app.go('/events/4/staffing');
    const items = app.page.locator('[data-timeline-item]');
    const before = await items.first().getAttribute('aria-label');
    await items.first().focus();
    for (let step = 0; step < 4; step++) {
        await app.page.keyboard.press('Shift+ArrowLeft');
    }
    const after = await items.first().getAttribute('aria-label');
    app.check('Shift+ArrowLeft resizes', `${before} -> ${after}`, before !== after);
    await app.page.getByRole('button', { name: 'Save changes' }).click();
    await app.settle();
    await app.shot('planner-saved');

    // A controller books a shift on event 4 and shows interest in event 2.
    await app.signIn('Tower Controller');
    await app.go('/events/4');
    await app.page.getByRole('button', { name: 'Book' }).first().click();
    await app.settle();
    app.check('shift booked', 'shows "You"', (await app.page.getByText('You', { exact: true }).count()) > 0);
    await app.go('/events/2');
    await app.page.locator('label:has(input[type=checkbox])').first().click();
    await app.page.getByRole('button', { name: 'Add a time window' }).click();
    await app.page.locator('input[type=time]').nth(0).fill('18:00');
    await app.page.locator('input[type=time]').nth(1).fill('20:00');
    await app.page.getByRole('button', { name: 'Show interest' }).click();
    await app.settle();
    await app.shot('interest-shown');

    // Roster: drag the controller onto the first position and publish.
    await app.signIn('Hub Admin');
    await app.go('/events/2/staffing');
    await app.dragTo(
        app.page.locator('li[draggable=true]', { hasText: 'Tower Controller' }),
        app.page.locator('[data-timeline-row]').first(),
        { x: 0.02, y: 0.5 },
    );
    const rostered = await app.page.locator('[data-timeline-item]').count();
    app.check('controller dropped on roster', `${rostered} items`, rostered > 0);
    await app.page.getByRole('button', { name: /Save and publish|Publish roster/ }).click();
    await app.settle();
    await app.shot('roster-published');

    await app.signIn('Tower Controller');
    await app.go('/events/2');
    const rows = await app.page.locator('table tr', { hasText: 'Tower Controller' }).count();
    app.check('controller sees roster', `${rows} rows`, rows > 0);
    await app.shot('controller-event-2');
}
