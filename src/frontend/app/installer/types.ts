/**
 * Launch actions after stepper checked
 * Gets script version
 * @param route route to launch action ex: {method : 'POST', url: '../rest/install/action'}
 * @param body data json sent to the route
 * @param description decscription of install action
 * @param installPriority action order (/!\ NEVER USE 1 IT IS FOR ROUTE /installer/initCustom)
 */
export class StepAction {
    idStep: string;
    route: any;
    body: any;
    description: any;
    installPriority: 1 | 2 | 3;
}
