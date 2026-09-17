export function TowerEmblem({
  type,
  mini = false,
}: {
  type: 'ember' | 'stone' | 'mage';
  mini?: boolean;
}) {
  return (
    <div className={`roster-emblem roster-emblem-${type} ${mini ? 'roster-emblem-mini' : ''}`} aria-hidden="true">
      <i /><b /><span />
    </div>
  );
}
