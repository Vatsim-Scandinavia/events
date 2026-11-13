import type { Controller } from '@/types/staffing';

/**
 * A controller as they chose to be named, with their CID beside it, unless the name shown is
 * already the CID. The server decides which (User::showsCidBesideName).
 */
export default function ControllerName({ controller }: { controller: Controller }) {
    return (
        <>
            {controller.name}
            {controller.showCid && <span className="ml-2 text-xs text-muted-foreground">{controller.cid}</span>}
        </>
    );
}

/**
 * The same as plain text, for places that can only hold text, such as a select's options.
 */
export const controllerText = (controller: Controller) => (controller.showCid ? `${controller.name} ${controller.cid}` : controller.name);
