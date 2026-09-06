import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Trash2, Calculator, DollarSign, Hash, ArrowRight } from 'lucide-react';
import { salaryComponentsApi, type FormulaItem } from '@/services/api';

interface ComponentOption {
    id: string;
    code: string;
    name: string;
    type: string;
    type_label: string;
    default_value: number;
    default_value_formatted: string;
}

interface FormulaBuilderProps {
    value: FormulaItem[];
    onChange: (value: FormulaItem[]) => void;
    excludeComponentId?: string;
}

const OPERATORS = [
    { value: '+', label: 'Tambah (+)' },
    { value: '-', label: 'Kurang (-)' },
    { value: '*', label: 'Kali (×)' },
    { value: '/', label: 'Bagi (÷)' },
];

const OPERAND_TYPES = [
    { value: 'base_salary', label: 'Gaji Pokok', icon: DollarSign },
    { value: 'gross_salary', label: 'Gaji Kotor', icon: DollarSign },
    { value: 'component', label: 'Komponen Lain', icon: Calculator },
    { value: 'number', label: 'Angka', icon: Hash },
];

export default function FormulaBuilder({ value, onChange, excludeComponentId }: FormulaBuilderProps) {
    const [components, setComponents] = useState<ComponentOption[]>([]);
    const [loading, setLoading] = useState(false);

    // Load available components
    useEffect(() => {
        const loadComponents = async () => {
            setLoading(true);
            try {
                const response = await salaryComponentsApi.availableForReference(excludeComponentId);
                setComponents(response.data.data);
            } catch {
                // Ignore errors
            } finally {
                setLoading(false);
            }
        };
        loadComponents();
    }, [excludeComponentId]);

    const addOperand = (type: string) => {
        const newItem: FormulaItem = { type: type as FormulaItem['type'] };

        if (type === 'number') {
            newItem.value = 0;
        }

        // If there are existing items and the last one is an operand, add an operator first
        if (value.length > 0 && value[value.length - 1].type !== 'operator') {
            onChange([...value, { type: 'operator', value: '+' }, newItem]);
        } else {
            onChange([...value, newItem]);
        }
    };

    const addOperator = (op: string) => {
        // Can only add operator after an operand
        if (value.length === 0 || value[value.length - 1].type === 'operator') {
            return;
        }
        onChange([...value, { type: 'operator', value: op }]);
    };

    const updateItem = (index: number, updates: Partial<FormulaItem>) => {
        const newValue = [...value];
        newValue[index] = { ...newValue[index], ...updates };
        onChange(newValue);
    };

    const removeItem = (index: number) => {
        const newValue = [...value];
        newValue.splice(index, 1);

        // Clean up orphan operators
        // Remove leading operator
        while (newValue.length > 0 && newValue[0].type === 'operator') {
            newValue.shift();
        }
        // Remove trailing operator
        while (newValue.length > 0 && newValue[newValue.length - 1].type === 'operator') {
            newValue.pop();
        }
        // Remove consecutive operators
        for (let i = newValue.length - 1; i > 0; i--) {
            if (newValue[i].type === 'operator' && newValue[i - 1].type === 'operator') {
                newValue.splice(i, 1);
            }
        }

        onChange(newValue);
    };

    const getComponentById = (id: string) => components.find(c => c.id === id);

    const renderItem = (item: FormulaItem, index: number) => {
        if (item.type === 'operator') {
            return (
                <div key={index} className="flex items-center gap-1">
                    <Select
                        value={item.value as string}
                        onValueChange={(v) => updateItem(index, { value: v })}
                    >
                        <SelectTrigger className="w-20 h-8">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {OPERATORS.map(op => (
                                <SelectItem key={op.value} value={op.value}>
                                    {op.value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => removeItem(index)}
                    >
                        <Trash2 className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </div>
            );
        }

        if (item.type === 'base_salary') {
            return (
                <div key={index} className="flex items-center gap-1">
                    <Badge variant="secondary" className="h-8 px-3 flex items-center gap-1">
                        <DollarSign className="h-3 w-3" />
                        Gaji Pokok
                    </Badge>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => removeItem(index)}
                    >
                        <Trash2 className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </div>
            );
        }

        if (item.type === 'gross_salary') {
            return (
                <div key={index} className="flex items-center gap-1">
                    <Badge variant="secondary" className="h-8 px-3 flex items-center gap-1">
                        <DollarSign className="h-3 w-3" />
                        Gaji Kotor
                    </Badge>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => removeItem(index)}
                    >
                        <Trash2 className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </div>
            );
        }

        if (item.type === 'component') {
            const comp = item.id ? getComponentById(item.id) : null;
            return (
                <div key={index} className="flex items-center gap-1">
                    <Select
                        value={item.id || ''}
                        onValueChange={(v) => {
                            const selected = getComponentById(v);
                            updateItem(index, {
                                id: v,
                                code: selected?.code,
                                name: selected?.name,
                            });
                        }}
                    >
                        <SelectTrigger className="w-48 h-8">
                            <SelectValue placeholder="Pilih komponen">
                                {comp ? (
                                    <span className="flex items-center gap-1">
                                        <Calculator className="h-3 w-3" />
                                        {comp.code}
                                    </span>
                                ) : 'Pilih komponen'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {components.map(c => (
                                <SelectItem key={c.id} value={c.id}>
                                    <div className="flex flex-col">
                                        <span className="font-medium">{c.code}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {c.name} - {c.default_value_formatted}
                                        </span>
                                    </div>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {comp && (
                        <span className="text-xs text-muted-foreground">
                            ({comp.default_value_formatted})
                        </span>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => removeItem(index)}
                    >
                        <Trash2 className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </div>
            );
        }

        if (item.type === 'number') {
            return (
                <div key={index} className="flex items-center gap-1">
                    <Input
                        type="number"
                        className="w-28 h-8"
                        value={item.value as number || ''}
                        onChange={(e) => updateItem(index, { value: parseFloat(e.target.value) || 0 })}
                        placeholder="0"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={() => removeItem(index)}
                    >
                        <Trash2 className="h-3 w-3 text-muted-foreground" />
                    </Button>
                </div>
            );
        }

        return null;
    };

    // Calculate preview result (simplified - just for display)
    const calculatePreview = (): string => {
        if (value.length === 0) return '-';

        const parts: string[] = [];
        for (const item of value) {
            if (item.type === 'base_salary') {
                parts.push('{Gaji Pokok}');
            } else if (item.type === 'gross_salary') {
                parts.push('{Gaji Kotor}');
            } else if (item.type === 'component') {
                parts.push(`{${item.code || '?'}}`);
            } else if (item.type === 'number') {
                parts.push(String(item.value || 0));
            } else if (item.type === 'operator') {
                parts.push(` ${item.value} `);
            }
        }

        return parts.join('');
    };

    return (
        <div className="space-y-3">
            <Label>Rumus</Label>

            {/* Formula display */}
            <div className="min-h-[60px] rounded-md border bg-muted/30 p-3">
                {value.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Klik tombol di bawah untuk menambah elemen rumus
                    </p>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        {value.map((item, index) => renderItem(item, index))}
                    </div>
                )}
            </div>

            {/* Preview */}
            {value.length > 0 && (
                <div className="flex items-center gap-2 text-sm">
                    <ArrowRight className="h-4 w-4 text-muted-foreground" />
                    <code className="rounded bg-muted px-2 py-1">
                        {calculatePreview()}
                    </code>
                </div>
            )}

            {/* Add buttons */}
            <div className="flex flex-wrap gap-2">
                <div className="text-xs text-muted-foreground mr-2 self-center">Tambah:</div>
                {OPERAND_TYPES.map(type => (
                    <Button
                        key={type.value}
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => addOperand(type.value)}
                        disabled={loading}
                    >
                        <type.icon className="mr-1 h-3 w-3" />
                        {type.label}
                    </Button>
                ))}
            </div>

            {/* Quick operator buttons */}
            {value.length > 0 && value[value.length - 1].type !== 'operator' && (
                <div className="flex gap-1">
                    <div className="text-xs text-muted-foreground mr-2 self-center">Operator:</div>
                    {OPERATORS.map(op => (
                        <Button
                            key={op.value}
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-8 h-8 p-0"
                            onClick={() => addOperator(op.value)}
                        >
                            {op.value}
                        </Button>
                    ))}
                </div>
            )}

            {/* Info text */}
            <div className="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground space-y-1">
                <p><strong>Gaji Pokok</strong> = gaji pokok karyawan dari data kepegawaian (berbeda tiap karyawan)</p>
                <p><strong>Gaji Kotor</strong> = total semua pendapatan di slip gaji</p>
                <p><strong>Komponen Lain</strong> = nilai dari komponen gaji lain yang sudah dihitung</p>
            </div>
        </div>
    );
}
