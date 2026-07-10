/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';

export default function FormSearch({ setAttributes }) {
	return (
		<WPEntitySearch
			placeholder="Search for forms"
			entityType="postType"
			entitySubType="form"
			entityStatus={['publish', 'draft']}
			showType={false}
			showUrl={false}
			onSelect={(item) => {
				setAttributes({ ref: parseInt(item.entityId, 10) });
			}}
		/>
	);
}
