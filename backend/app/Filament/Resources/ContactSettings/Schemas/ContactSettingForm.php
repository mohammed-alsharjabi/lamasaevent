<?php

namespace App\Filament\Resources\ContactSettings\Schemas;

use App\Models\ContactSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ContactSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('أين تظهر هذه البيانات؟')
                ->description('تظهر أرقام الاتصال وواتساب في الفوتر والأزرار العائمة وصفحات الخدمات والتواصل. بعد الحفظ يبدأ النشر تلقائيًا، ويظهر التحديث عادة خلال أقل من دقيقة.')
                ->schema([
                    Placeholder::make('contact_preview')
                        ->label('معاينة سريعة لما سيراه الزائر')
                        ->content(fn (Get $get): HtmlString => self::preview($get)),
                ])
                ->columnSpanFull(),

            Section::make('الاتصال المباشر')
                ->description('اكتب الأرقام بصيغة دولية. عند تغيير رقم الاتصال نحدّث شكله الظاهر تلقائيًا، ويمكنك تعديل طريقة العرض بعد ذلك.')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')
                        ->label('رقم الاتصال الأساسي')
                        ->helperText('مثال: +966501234567 — يُستخدم عند الضغط على زر «اتصال».')
                        ->placeholder('+9665xxxxxxxx')
                        ->tel()
                        ->required()
                        ->maxLength(30)
                        ->live(onBlur: true)
                        ->afterStateUpdated(
                            fn (?string $state, Set $set) => $set(
                                'phone_display',
                                ContactSetting::formatPhoneForDisplay((string) $state),
                            ),
                        )
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('phone_display')
                        ->label('شكل الرقم الذي يراه الزائر')
                        ->helperText('يتحدث تلقائيًا من الرقم الأساسي. عدّله فقط إذا أردت مسافات أو تنسيقًا مختلفًا.')
                        ->placeholder('050 123 4567')
                        ->required()
                        ->maxLength(30)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('whatsapp')
                        ->label('رقم واتساب')
                        ->helperText('أرقام فقط مع رمز الدولة، دون + أو مسافات. مثال: 966501234567.')
                        ->placeholder('9665xxxxxxxx')
                        ->required()
                        ->maxLength(30)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('email')
                        ->label('البريد الإلكتروني لاستقبال الطلبات')
                        ->helperText('يظهر كرابط بريد في الفوتر وصفحة التواصل.')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columnSpanFull(),

            Section::make('الموقع الجغرافي')
                ->description('تُستخدم هذه البيانات في صفحة التواصل وبيانات النشاط لمحركات البحث.')
                ->columns(3)
                ->schema([
                    TextInput::make('city')
                        ->label('المدينة')
                        ->placeholder('الرياض')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('region')
                        ->label('المنطقة الإدارية')
                        ->placeholder('منطقة الرياض')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('country_code')
                        ->label('رمز الدولة المختصر')
                        ->helperText('حرفان فقط، مثل SA للسعودية.')
                        ->placeholder('SA')
                        ->required()
                        ->length(2)
                        ->dehydrateStateUsing(
                            fn (?string $state): ?string => $state ? strtoupper($state) : null,
                        )
                        ->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columnSpanFull(),

            Section::make('حسابات التواصل الاجتماعي')
                ->description('كل الحقول اختيارية. الصق الرابط الكامل للحساب؛ الحقول الفارغة لا تظهر في الموقع.')
                ->columns(2)
                ->schema([
                    self::socialField('instagram', 'إنستغرام', 'https://instagram.com/...'),
                    self::socialField('tiktok', 'تيك توك', 'https://tiktok.com/@...'),
                    self::socialField('snapchat', 'سناب شات', 'https://snapchat.com/add/...'),
                    self::socialField('x', 'إكس (تويتر)', 'https://x.com/...'),
                    self::socialField('facebook', 'فيسبوك', 'https://facebook.com/...'),
                    self::socialField('youtube', 'يوتيوب', 'https://youtube.com/@...'),
                    self::socialField('linkedin', 'لينكدإن', 'https://linkedin.com/company/...'),
                ])
                ->columnSpanFull(),
        ])->columns(1);
    }

    private static function socialField(
        string $network,
        string $label,
        string $placeholder,
    ): TextInput {
        return TextInput::make("social_links.{$network}")
            ->label($label)
            ->placeholder($placeholder)
            ->url()
            ->maxLength(500)
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    private static function preview(Get $get): HtmlString
    {
        $phone = e((string) ($get('phone_display') ?: 'سيظهر رقم الاتصال هنا'));
        $email = e((string) ($get('email') ?: 'سيظهر البريد الإلكتروني هنا'));
        $city = e((string) ($get('city') ?: 'المدينة'));

        return new HtmlString(
            '<div style="display:grid;gap:.55rem;padding:1rem 1.1rem;'
            .'border:1px solid #d1d5db;border-radius:.85rem;background:#f9fafb">'
            .'<strong style="color:#111827">'.$phone.'</strong>'
            .'<span style="color:#4b5563">'.$email.'</span>'
            .'<small style="color:#6b7280">'.$city
            .' · ستظهر البيانات في الفوتر وأزرار التواصل وصفحة التواصل.</small>'
            .'</div>',
        );
    }
}
