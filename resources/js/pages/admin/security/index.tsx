import type { ReactNode } from 'react';
import SecuritySettings from '@/components/security/security-settings';
import type { SecurityProps } from '@/components/security/security-settings';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminSecurity(props: SecurityProps) {
    return <SecuritySettings {...props} />;
}

AdminSecurity.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
