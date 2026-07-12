<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClubApplicationController from '@/actions/App/Http/Controllers/ClubApplicationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="Înscriere club" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]"
    >
        <Card class="w-full max-w-2xl">
            <CardHeader>
                <CardTitle>Înscrie-ți clubul</CardTitle>
                <CardDescription>
                    Completează formularul de mai jos. Cererea ta va fi
                    evaluată, iar după aprobare vei primi acces în panoul de
                    club.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ClubApplicationController.store.form()"
                    reset-on-success
                    class="space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="club_name">Nume club *</Label>
                            <Input id="club_name" name="club_name" required />
                            <InputError :message="errors.club_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="fiscal_code">CIF *</Label>
                            <Input id="fiscal_code" name="fiscal_code" required />
                            <InputError :message="errors.fiscal_code" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="contact_name">Persoană de contact *</Label>
                            <Input id="contact_name" name="contact_name" required />
                            <InputError :message="errors.contact_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact_role">Funcție</Label>
                            <Input id="contact_role" name="contact_role" />
                            <InputError :message="errors.contact_role" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="contact_email">Email *</Label>
                            <Input
                                id="contact_email"
                                type="email"
                                name="contact_email"
                                required
                            />
                            <InputError :message="errors.contact_email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact_phone">Telefon</Label>
                            <Input id="contact_phone" name="contact_phone" />
                            <InputError :message="errors.contact_phone" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="county">Județ</Label>
                            <Input id="county" name="county" />
                            <InputError :message="errors.county" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="city">Oraș</Label>
                            <Input id="city" name="city" />
                            <InputError :message="errors.city" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="message">Mesaj / detalii</Label>
                        <textarea
                            id="message"
                            name="message"
                            rows="4"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <InputError :message="errors.message" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <Link
                            href="/"
                            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            ← Înapoi
                        </Link>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Se trimite...' : 'Trimite cererea' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
