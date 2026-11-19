import type { ReactNode } from 'react';
import SecuritySettings from '@/components/security/security-settings';
import type { SecurityProps } from '@/components/security/security-settings';
import UserLayout from '@/layouts/user-layout';

export default function UserSecurity(props: SecurityProps) {
    return <SecuritySettings {...props} />;
}

UserSecurity.layout = (page: ReactNode) => <UserLayout>{page}</UserLayout>;
