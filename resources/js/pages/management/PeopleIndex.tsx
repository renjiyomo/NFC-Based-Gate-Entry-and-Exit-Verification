import { Link } from '@inertiajs/react';
import { Badge, Panel } from '../../components/ui';
import {
    AddLink,
    DataTable,
    DirectoryFilters,
    ManagementPage,
    Pagination,
    Portrait,
    RecordBadge,
} from '../../components/management/Common';
import type { Filters, Paginated, PersonDirectory, PersonRecord } from '../../lib/management';

export default function PeopleIndex({
    directory,
    records,
    filters,
}: {
    directory: PersonDirectory;
    records: Paginated<PersonRecord>;
    filters: Filters;
}) {
    const student = directory === 'students';
    const base = `/admin/${directory}`;
    return (
        <ManagementPage
            page={directory}
            title={student ? 'Student directory' : 'Employee directory'}
            description="Manage student and employee identities and their NFC credentials."
            action={
                <AddLink href={`${base}/create`}>Add {student ? 'student' : 'employee'}</AddLink>
            }
        >
            <Panel
                title={student ? 'Students' : 'Employees'}
                subtitle="Identity records and NFC status"
            >
                <DirectoryFilters
                    filters={filters}
                    url={base}
                    groupLabel={student ? 'Program contains' : 'Department contains'}
                />
                <DataTable
                    caption={`${directory} directory`}
                    columns={[
                        'Person',
                        student ? 'Student number' : 'Employee number',
                        student ? 'Program / year' : 'Department / position',
                        'Record status',
                        'NFC credential',
                        'Details',
                    ]}
                    empty={`No ${directory} match these filters`}
                    count={records.data.length}
                >
                    {records.data.map((person) => (
                        <tr key={person.id}>
                            <td>
                                <div className="identity-cell">
                                    <Portrait src={person.photo_url} name={person.full_name} />
                                    <strong>{person.full_name}</strong>
                                </div>
                            </td>
                            <td>{person.campus_number}</td>
                            <td>
                                {student ? (
                                    <>
                                        {person.program}
                                        <small>
                                            {person.year_level
                                                ? `Year ${person.year_level}`
                                                : 'Year not specified'}
                                        </small>
                                    </>
                                ) : (
                                    <>
                                        {person.department}
                                        <small>{person.position}</small>
                                    </>
                                )}
                            </td>
                            <td>
                                <RecordBadge status={person.status} archived={person.archived_at} />
                            </td>
                            <td>
                                <Badge tone={person.nfc_credential ? 'success' : 'neutral'}>
                                    {person.nfc_credential
                                        ? `Active · ${person.nfc_credential.label}`
                                        : 'No active NFC credential'}
                                </Badge>
                            </td>
                            <td>
                                <Link
                                    href={`${base}/${person.id}`}
                                    className="row-link"
                                    aria-label={`View ${person.full_name}`}
                                >
                                    View record
                                </Link>
                            </td>
                        </tr>
                    ))}
                </DataTable>
                <Pagination records={records} />
            </Panel>
            <p className="availability-note">
                An active person and selected active NFC credential are required for scanning.
            </p>
        </ManagementPage>
    );
}
