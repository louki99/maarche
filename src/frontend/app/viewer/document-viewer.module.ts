import { NgModule } from '@angular/core';

import { SharedModule } from '../app-common.module';

import { DocumentViewerComponent } from './document-viewer.component';
import { DocumentViewerModalComponent } from './modal/document-viewer-modal.component';

import { EcplOnlyofficeViewerComponent } from '@plugins/onlyoffice-api-js/onlyoffice-viewer.component';

import { PdfViewerModule } from 'ng2-pdf-viewer';
import { CollaboraOnlineViewerComponent } from '@plugins/collabora-online/collabora-online-viewer.component';

import { InternationalizationModule } from '@service/translate/internationalization.module';
import { TranslateService } from '@ngx-translate/core';
import { Office365SharepointViewerComponent } from '@plugins/office365-sharepoint/office365-sharepoint-viewer.component';

@NgModule({
    imports: [
        SharedModule,
        PdfViewerModule,
        InternationalizationModule
    ],
    declarations: [
        DocumentViewerComponent,
        EcplOnlyofficeViewerComponent,
        DocumentViewerModalComponent,
        CollaboraOnlineViewerComponent,
        Office365SharepointViewerComponent
    ],
    exports: [
        PdfViewerModule,
        EcplOnlyofficeViewerComponent,
        DocumentViewerComponent,
        CollaboraOnlineViewerComponent,
        Office365SharepointViewerComponent
    ]
})
export class DocumentViewerModule {
    constructor(translate: TranslateService) {
        translate.setDefaultLang('fr');
    }
}
