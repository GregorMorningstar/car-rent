import { Link, usePage } from '@inertiajs/react';
import verification from '@/routes/verification';

export default function EmailVerificationBanner() {
    const { auth } = usePage().props;

    if (!auth.user || auth.user.email_verified_at) {
        return null;
    }

    return (
        <div className="border-b border-amber-200 bg-amber-50">
            <p className="gutter py-3 text-sm text-amber-800">
                Twój adres e-mail nie jest potwierdzony.{' '}
                <Link
                    href={verification.notice()}
                    className="font-medium underline"
                >
                    Potwierdź go
                </Link>
                , aby móc rezerwować samochody.
            </p>
        </div>
    );
}
