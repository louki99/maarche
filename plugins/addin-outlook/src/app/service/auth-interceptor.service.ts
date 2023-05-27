import { Injectable } from '@angular/core';
import { HttpHandler, HttpInterceptor, HttpRequest, HttpClient, HttpErrorResponse } from '@angular/common/http';
import { catchError, filter, switchMap, take } from 'rxjs/operators';
import { NotificationService } from './notification/notification.service';
import { AuthService } from './auth.service';
import { BehaviorSubject, Observable } from 'rxjs';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
    private isRefreshing = false;
    private refreshTokenSubject: BehaviorSubject<any> = new BehaviorSubject<any>(
        null
    );

    constructor(
        public http: HttpClient,
        public notificationService: NotificationService,
        public authService: AuthService,
    ) { }

    addAuthHeader(request: HttpRequest<any>) {

        const authHeader = this.authService.getToken();

        return request.clone({
            setHeaders: {
                'Authorization': 'Bearer ' + authHeader
            }
        });
    }

    private handle401Error(request: HttpRequest<any>, next: HttpHandler) {
        if (!this.isRefreshing) {
            this.isRefreshing = true;
            this.refreshTokenSubject.next(null);

            return this.authService.refreshToken().pipe(
                switchMap((data: any) => {
                    this.isRefreshing = false;
                    this.refreshTokenSubject.next(data.token);
                    request = this.addAuthHeader(request);
                    return next.handle(request);
                })
            );
        } else {
            return this.refreshTokenSubject.pipe(
                filter((token) => token != null),
                take(1),
                switchMap(() => {
                    request = this.addAuthHeader(request);
                    return next.handle(request);
                })
            );
        }
    }

    intercept(request: HttpRequest<any>, next: HttpHandler): Observable<any> {
        // Add current token in header request
        request = this.addAuthHeader(request);

        // Handle response
        return next.handle(request).pipe(
            catchError(error => {
                // Disconnect user if bad token process
                if (error.status === 401) {
                    return this.handle401Error(request, next);
                } else {
                    const response = new HttpErrorResponse({
                        error: error.error,
                        status: error.status,
                        statusText: error.statusText,
                        headers: error.headers,
                        url: error.url,
                    });
                    return Promise.reject(response);
                }
            })
        );
    }
}
