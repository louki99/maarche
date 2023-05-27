import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { NotificationService } from '@service/notification/notification.service';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';

declare let $: any;

@Component({
    templateUrl: 'print-separator.component.html',
    styleUrls: ['print-separator.component.scss'],
})
export class PrintSeparatorComponent implements OnInit {

    @ViewChild('snav', { static: true }) sidenavLeft: MatSidenav;
    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;

    entities: any[] = [];
    entitiesChosen: any[] = [];
    loading: boolean = false;
    docUrl: string = '';
    docData: string = '';
    docBuffer: ArrayBuffer = null;
    separatorTypes: string[] = ['barcode', 'qrcode'];
    separatorTargets: string[] = ['entities', 'generic'];

    separator: any = {
        type: 'qrcode',
        target: 'entities',
        entities: []
    };

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService
    ) {
        (<any>window).pdfWorkerSrc = 'pdfjs/pdf.worker.min.js';
    }

    ngOnInit(): void {

        this.headerService.setHeader(this.translate.instant('lang.printSeparators'));

        this.http.get('../rest/entities')
            .subscribe((data: any) => {
                this.entities = data['entities'];
                this.entities.forEach(entity => {
                    entity.state.disabled = false;
                });
                this.loadEntities();

            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    loadEntities() {

        setTimeout(() => {
            $('#jstree')
                .on('select_node.jstree', (e: any, data: any) => {
                    this.separator.entities = $('#jstree').jstree('get_checked', null, true); // to trigger disable button if no entities
                })
                .on('deselect_node.jstree', (e: any, data: any) => {
                    this.separator.entities = $('#jstree').jstree('get_checked', null, true); // to trigger disable button if no entities
                })
                .jstree({
                    'checkbox': {
                        'three_state': false // no cascade selection
                    },
                    'core': {
                        force_text: true,
                        'themes': {
                            'name': 'proton',
                            'responsive': true
                        },
                        'data': this.entities,
                    },
                    'plugins': ['checkbox', 'search', 'sort']
                });
            let to: any = false;
            $('#jstree_search').keyup(function () {
                if (to) {
                    clearTimeout(to);
                }
                to = setTimeout(function () {
                    const v: any = $('#jstree_search').val();
                    $('#jstree').jstree(true).search(v);
                }, 250);
            });
            $('#jstree')
                // create the instance
                .jstree();
        }, 0);
    }

    generateSeparators() {
        this.loading = true;
        this.separator.entities = $('#jstree').jstree('get_checked', null, true);
        this.http.post('../rest/entitySeparators', this.separator)
            .subscribe((data: any) => {
                this.docData = data;
                this.docBuffer = this.base64ToArrayBuffer(this.docData);
                this.downloadSeparators();
                this.loading = false;
            }, (err: any) => {
                this.notify.handleErrors(err);
            });
    }

    base64ToArrayBuffer(base64: any) {
        const binary_string = window.atob(base64);
        const len = binary_string.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    changeType(type: any) {
        this.docBuffer = null;
        if (type.value === 'entities') {
            this.entities.forEach(entity => {
                entity.state.disabled = false;
            });
            $('#jstree').jstree(true).settings.core.data = this.entities;
            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('refresh');
        } else {
            this.entities.forEach(entity => {
                entity.state.disabled = true;
            });
            $('#jstree').jstree(true).settings.core.data = this.entities;
            $('#jstree').jstree('deselect_all');
            $('#jstree').jstree('refresh');
        }
    }

    downloadSeparators() {
        const a = document.createElement('a');
        document.body.appendChild(a);
        a.style.display = 'none';

        const url = `data:application/pdf;base64,${this.docData}`;
        a.href = url;
        a.download = this.functions.getFormatedFileName(this.translate.instant('lang.separators'), 'pdf');
        a.click();
        window.URL.revokeObjectURL(url);
    }
}
