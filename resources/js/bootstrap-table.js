import bootstrapTable from 'bootstrap-table';

import bootstrapTableEnUS from 'bootstrap-table/dist/locale/bootstrap-table-en-US.js';
import bootstrapTableFilterControl from 'bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.js';
import bootstrapTableCookie from 'bootstrap-table/dist/extensions/cookie/bootstrap-table-cookie.js';

// Bootstrap-table: Filter function to strip html from bootstrap table column filters
window.tableFilterStripHtml = function (value) {
    return value.replace(/<[^>]+>/g, '').trim();
}