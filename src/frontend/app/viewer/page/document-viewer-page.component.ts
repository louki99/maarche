import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
    selector: 'app-document-viewer-page',
    templateUrl: './document-viewer-page.component.html',
    styleUrls: ['./document-viewer-page.component.scss']
})
export class DocumentViewerPageComponent implements OnInit {

    loading: boolean = true;
    resId: number = 0;

    constructor(
        private http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
    ) { }

    ngOnInit() {
        this.route.params.subscribe(params => {
            if (typeof params['resId'] !== 'undefined') {
                this.resId = params['resId'];
                this.http.get(`../rest/resources/${this.resId}/fileInformation`).pipe(
                    tap((data: any) => {
                        this.loading = false;
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        this.router.navigate(['/home']);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.router.navigate(['/home']);
            }
        });
    }

}
