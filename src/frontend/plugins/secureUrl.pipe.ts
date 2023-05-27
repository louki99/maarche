import { Pipe, PipeTransform } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from '@service/auth.service';
import { Observable, of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';

@Pipe({
    name: 'secureUrl'
})
export class SecureUrlPipe implements PipeTransform {

    constructor(
        private http: HttpClient,
        private authService: AuthService,
        private notify: NotificationService,
    ) { }

    transform(url: string) {
        const headers = new HttpHeaders({
            'Authorization': 'Bearer ' + this.authService.getToken()
        });

        return new Observable<string>((observer) => {
            // This is a tiny blank image
            observer.next('data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

            if (url !== undefined) {
                // The next and error callbacks from the observer
                const { next, error } = observer;

                this.http.get(url, { headers: headers, responseType: 'blob' }).pipe(
                    tap((response) => {
                        const reader = new FileReader();
                        reader.readAsDataURL(response);
                        reader.onloadend = () => {
                            observer.next(reader.result as any);
                        };
                    }),
                    catchError(async (err: any) => {
                        const defaultImage = await this.loadDefaultImage();
                        observer.next(defaultImage);
                        this.notify.handleBlobErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
            return { unsubscribe() { } };
        });
    }

    loadDefaultImage(): Promise<string> {
        return new Promise((resolve) => {
            this.http.get('assets/noThumbnail.png', { responseType: 'blob' }).pipe(
                tap((response) => {
                    const reader = new FileReader();
                    reader.readAsDataURL(response);
                    reader.onloadend = () => {
                        resolve(reader.result as any);
                    };
                }),
                catchError((err: any) => {
                    resolve('data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
                    this.notify.handleBlobErrors(err);
                    return of(false);
                })
            ).subscribe();
        });

    }
}
